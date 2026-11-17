<?php

namespace Tests\Feature\Laboratories;

use App\Enums\MediaPurpose;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Models\LabBooking;
use App\Models\Laboratory;
use App\Models\User;
use App\Services\Laboratories\LabRequests;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class PortalLabBookingTest extends TestCase
{
    use MakesLaboratories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeLabSeams();
    }

    private function book(Laboratory $laboratory, User $patient, bool $paid = true, array $overrides = []): LabBooking
    {
        $id = $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC'], $overrides))
            ->assertCreated()
            ->json('data.id');

        $booking = LabBooking::query()->where('uuid', $id)->firstOrFail();

        if ($paid) {
            $this->payments->pay($booking);
        }

        return $booking->refresh();
    }

    private function results(User $owner): string
    {
        $library = app(MediaLibrary::class);
        $file = UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n%%EOF\n");

        return $library->url($library->upload($owner, MediaPurpose::LabResults, $file, []));
    }

    public function test_the_laboratory_sees_a_booking_only_once_paid(): void
    {
        [$laboratory, $account] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $doctor = User::factory()->app(Role::Doctor)->create(['full_name' => 'Dr Bello Musa']);
        $request = app(LabRequests::class)->create($doctor, $patient, fake()->uuid(), 'MDCN/1', [['test_code' => 'FBC']], 'Fast overnight');

        $unpaid = $this->book($laboratory, $patient, paid: false);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/bookings')
            ->assertJsonPath('data.total', 0);

        $this->actingAs($account)
            ->getJson("api/v1/portal/laboratory/bookings/{$unpaid->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_BOOKING_NOT_FOUND');

        $this->payments->pay($unpaid);
        $this->actingAs($patient)->postJson("api/v1/labs/bookings/{$unpaid->uuid}/cancel")->assertOk();

        $paid = $this->book($laboratory, $patient, overrides: ['request_id' => $request->uuid]);

        $this->actingAs($account)
            ->getJson("api/v1/portal/laboratory/bookings/{$paid->uuid}")
            ->assertOk()
            ->assertJsonPath('data.patient_name', $patient->full_name)
            ->assertJsonPath('data.patient_phone', $patient->phone)
            ->assertJsonPath('data.doctor_name', 'Dr Bello Musa')
            ->assertJsonPath('data.request_notes', 'Fast overnight')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.cancellation', null);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/bookings')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.cancellation.by', 'patient');

        $this->actingAs($account)->getJson('api/v1/portal/laboratory/bookings?status=open')->assertJsonPath('data.total', 1);
        $this->actingAs($account)->getJson('api/v1/portal/laboratory/bookings?status=cancelled')->assertJsonPath('data.total', 1);
        $this->actingAs($account)->getJson('api/v1/portal/laboratory/bookings?q=' . $paid->reference)->assertJsonPath('data.total', 1);
        $this->actingAs($account)->getJson('api/v1/portal/laboratory/bookings?q=' . substr($patient->phone, 3))->assertJsonPath('data.total', 2);
        $this->actingAs($account)->getJson('api/v1/portal/laboratory/bookings?status=lost')->assertStatus(400);
    }

    public function test_a_booking_moves_one_step_at_a_time_to_results(): void
    {
        [$laboratory, $account] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $booking = $this->book($laboratory, $patient);
        $advance = fn(array $body = []) => $this->actingAs($account)
            ->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/advance", $body);

        $advance()->assertOk()->assertJsonPath('data.status', 'sample_collected');
        $advance()->assertOk()->assertJsonPath('data.status', 'processing');

        $advance()
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'RESULTS_REQUIRED')
            ->assertJsonPath('error.field', 'results_url');

        [, $other] = $this->laboratory();

        $advance(['results_url' => $this->results($other)])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'results_url');

        $url = $this->results($account);

        $advance(['results_url' => $url, 'results_name' => 'FBC report.pdf'])
            ->assertOk()
            ->assertJsonPath('data.status', 'results_ready')
            ->assertJsonPath('data.results_name', 'FBC report.pdf');

        $advance()
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_FINAL');

        $resultsUrl = $this->actingAs($patient)
            ->getJson("api/v1/labs/bookings/{$booking->uuid}")
            ->assertJsonPath('data.status', 'results_ready')
            ->json('data.results_url');

        $this->assertStringContainsString('signature=', $resultsUrl);
        $this->get($resultsUrl)->assertOk();

        $pushes = collect($this->notifier->sent)->where('to', $patient->id)->values();

        $this->assertCount(3, $pushes);
        $this->assertSame(NotificationCategory::LabResult, $pushes[2]['category']);
        $this->assertSame('Your laboratory results are ready', $pushes[2]['title']);
        $this->assertSame($booking->uuid, $pushes[2]['data']['target_id']);
        $this->assertStringNotContainsString('FBC', $pushes[2]['body']);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/summary')
            ->assertOk()
            ->assertJsonPath('data', [
                'booked_today' => 0,
                'awaiting_results' => 0,
                'results_this_week' => 1,
                'tests_listed' => 1,
                'tests_unavailable' => 0,
            ]);
    }

    public function test_the_laboratory_cancels_while_booked_and_the_patient_is_refunded(): void
    {
        [$laboratory, $account] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $booking = $this->book($laboratory, $patient, overrides: ['scheduled_for' => now()->addMinutes(5)->toIso8601String()]);
        $started = $this->book($laboratory, $patient);

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/summary')
            ->assertJsonPath('data.booked_today', now(config('elevenmed.timezone'))->addMinutes(5)->isToday() ? 1 : 0);

        $this->actingAs($account)
            ->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/cancel", ['reason' => 'no'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reason');

        $this->actingAs($account)
            ->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/cancel", ['reason' => 'Our analyser is down this week'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation', ['by' => 'laboratory', 'reason' => 'Our analyser is down this week'])
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame('The laboratory cancelled your booking', collect($this->notifier->sent)->last()['title']);

        $this->actingAs($account)
            ->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/advance")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_CANCELLED');

        $this->actingAs($account)->postJson("api/v1/portal/laboratory/bookings/{$started->uuid}/advance")->assertOk();

        $this->actingAs($account)
            ->postJson("api/v1/portal/laboratory/bookings/{$started->uuid}/cancel", ['reason' => 'Changed our mind'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_STARTED');
    }

    public function test_another_laboratorys_booking_is_not_found(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        [, $other] = $this->laboratory();
        $booking = $this->book($laboratory, $this->labPatient());

        $this->actingAs($other)->getJson("api/v1/portal/laboratory/bookings/{$booking->uuid}")->assertNotFound();
        $this->actingAs($other)->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/advance")->assertNotFound();
        $this->actingAs($other)->postJson("api/v1/portal/laboratory/bookings/{$booking->uuid}/cancel", ['reason' => 'Not ours'])->assertNotFound();
        $this->actingAs($this->labPatient())->getJson('api/v1/portal/laboratory/bookings')->assertForbidden();
    }
}
