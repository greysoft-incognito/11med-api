<?php

namespace Tests\Feature\Laboratories;

use App\Enums\LabBookingStatus;
use App\Enums\NotificationCategory;
use App\Enums\Role;
use App\Models\LabBooking;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use App\Services\Laboratories\BookingPayables;
use App\Services\Laboratories\LabRequests;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class LabBookingTest extends TestCase
{
    use MakesLaboratories;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeLabSeams();
    }

    private function request(User $patient, array $tests = [['test_code' => 'FBC', 'reason' => 'Tired all the time']])
    {
        $doctor = User::factory()->app(Role::Doctor)->create(['full_name' => 'Dr Bello Musa']);

        return app(LabRequests::class)->create($doctor, $patient, $this->consultationBetween($doctor, $patient), 'MDCN/12345', $tests, 'Fast overnight');
    }

    public function test_a_patient_books_priced_from_the_laboratorys_list_and_born_unpaid(): void
    {
        $this->labTest('LIPID', ['name' => 'Lipid profile', 'fasting_hours' => 12]);
        [$laboratory] = $this->laboratory(['FBC' => 300000, 'LIPID' => 800000], [], ['home_collection_fee_kobo' => 200000]);
        $patient = $this->labPatient();

        $booking = $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['fbc', 'LIPID', 'FBC'], [
                'collection' => 'home',
                'address' => ['line1' => '4 Adeola Odeku', 'line2' => null, 'city' => 'Victoria Island', 'state' => 'Lagos', 'directions' => 'Blue gate'],
                'channel' => 'card',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.laboratory_id', $laboratory->organisation->uuid)
            ->assertJsonPath('data.laboratory_name', $laboratory->organisation->name)
            ->assertJsonPath('data.tests', [
                ['test_code' => 'FBC', 'test_name' => 'Test FBC', 'price_kobo' => 300000],
                ['test_code' => 'LIPID', 'test_name' => 'Lipid profile', 'price_kobo' => 800000],
            ])
            ->assertJsonPath('data.tests_kobo', 1100000)
            ->assertJsonPath('data.collection_fee_kobo', 200000)
            ->assertJsonPath('data.total_kobo', 1300000)
            ->assertJsonPath('data.fasting_hours', 12)
            ->assertJsonPath('data.address.directions', 'Blue gate')
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.request_id', null)
            ->assertJsonPath('data.results_url', null)
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.amount_kobo', 1300000)
            ->assertJsonPath('data.payment.channel', 'card')
            ->json('data');

        $this->assertMatchesRegularExpression('/^11L-[A-Z0-9]{8}$/', $booking['reference']);

        $this->actingAs($patient)
            ->getJson('api/v1/labs/bookings')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $booking['id'])
            ->assertJsonPath('data.items.0.payment.status', 'pending');

        $this->actingAs($patient)
            ->getJson("api/v1/labs/bookings/{$booking['id']}")
            ->assertOk()
            ->assertJsonPath('data.reference', $booking['reference']);

        $targets = app(BookingPayables::class)->resolve($booking['id'], $patient);

        $this->assertSame($booking['id'], $targets[0]->paymentTargetId());
        $this->assertSame(1300000, $targets[0]->amountKobo());
        $this->assertSame($booking['reference'], $targets[0]->paymentReference());
    }

    public function test_booking_refusals(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000, 'MP' => [100000, false]]);
        $this->labTest('LFT');
        $patient = $this->labPatient();
        $book = fn(array $codes, array $overrides = []) => $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, $codes, $overrides));

        $book(['FBC', 'LFT'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TEST_NOT_OFFERED')
            ->assertJsonPath('error.meta.test_code', 'LFT');

        $book(['NOPE'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TEST_NOT_OFFERED');

        $book(['MP'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'TEST_UNAVAILABLE')
            ->assertJsonPath('error.meta.test_code', 'MP');

        $book(['FBC'], ['collection' => 'home'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'HOME_COLLECTION_UNAVAILABLE');

        $laboratory->update(['home_collection_fee_kobo' => 0]);

        $book(['FBC'], ['collection' => 'home'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'address');

        $book(['FBC'], ['scheduled_for' => now()->subHour()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SCHEDULE_PAST')
            ->assertJsonPath('error.field', 'scheduled_for');

        $book([])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'test_codes');

        $book(['FBC'], ['laboratory_id' => fake()->uuid()])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_NOT_FOUND');

        $book(['FBC'], ['request_id' => fake()->uuid()])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'LAB_REQUEST_NOT_FOUND');

        $this->assertSame(0, LabBooking::query()->count());
    }

    public function test_a_doctors_request_is_booked_once_and_reopened_by_cancelling(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $request = $this->request($patient);

        $sent = collect($this->notifier->sent)->firstWhere('category', NotificationCategory::LabRequest);

        $this->assertSame($request->uuid, $sent['data']['target_id']);
        $this->assertStringNotContainsString('FBC', $sent['body']);

        $this->actingAs($patient)
            ->getJson('api/v1/labs/requests')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $request->uuid)
            ->assertJsonPath('data.items.0.mdcn_number', 'MDCN/12345')
            ->assertJsonPath('data.items.0.doctor_name', 'Dr Bello Musa')
            ->assertJsonPath('data.items.0.tests.0.reason', 'Tired all the time')
            ->assertJsonPath('data.items.0.booking_id', null);

        $booking = $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC'], ['request_id' => $request->uuid]))
            ->assertCreated()
            ->assertJsonPath('data.request_id', $request->uuid)
            ->json('data');

        $this->actingAs($patient)
            ->getJson("api/v1/labs/requests/{$request->uuid}")
            ->assertJsonPath('data.booking_id', $booking['id']);

        $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC'], ['request_id' => $request->uuid]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'REQUEST_ALREADY_BOOKED')
            ->assertJsonPath('error.meta.booking_id', $booking['id']);

        $this->actingAs($patient)
            ->postJson("api/v1/labs/bookings/{$booking['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment.status', 'abandoned');

        $this->assertSame([['target' => $booking['id'], 'amount' => null]], $this->payments->refunds);

        $this->actingAs($patient)
            ->getJson("api/v1/labs/requests/{$request->uuid}")
            ->assertJsonPath('data.booking_id', null);

        $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC'], ['request_id' => $request->uuid]))
            ->assertCreated();
    }

    public function test_cancelling_a_paid_booking_refunds_it_and_a_started_one_is_refused(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $book = fn() => $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))
            ->json('data.id');

        $paid = LabBooking::query()->where('uuid', $book())->first();
        $this->payments->pay($paid);

        $this->actingAs($patient)
            ->postJson("api/v1/labs/bookings/{$paid->uuid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->actingAs($patient)
            ->postJson("api/v1/labs/bookings/{$paid->uuid}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_CANCELLED');

        $started = LabBooking::query()->where('uuid', $book())->first();
        $started->update(['status' => LabBookingStatus::SampleCollected]);

        $this->actingAs($patient)
            ->postJson("api/v1/labs/bookings/{$started->uuid}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BOOKING_STARTED');
    }

    public function test_bookings_and_requests_are_the_patients_own(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $owner = $this->labPatient();
        $other = $this->labPatient();
        $request = $this->request($owner);
        $bookingId = $this->actingAs($owner)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))
            ->json('data.id');

        $this->actingAs($other)->getJson("api/v1/labs/bookings/{$bookingId}")->assertNotFound()->assertJsonPath('error.code', 'LAB_BOOKING_NOT_FOUND');
        $this->actingAs($other)->postJson("api/v1/labs/bookings/{$bookingId}/cancel")->assertNotFound();
        $this->actingAs($other)->getJson("api/v1/labs/requests/{$request->uuid}")->assertNotFound()->assertJsonPath('error.code', 'LAB_REQUEST_NOT_FOUND');
        $this->actingAs($other)->getJson('api/v1/labs/bookings')->assertJsonPath('data.total', 0);

        $this->actingAs($other)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC'], ['request_id' => $request->uuid]))
            ->assertNotFound();

        $doctor = User::factory()->app(Role::Doctor)->create();

        $this->actingAs($doctor)->getJson('api/v1/labs/bookings')->assertForbidden();
        $this->actingAs($doctor)->postJson('api/v1/labs/bookings', [])->assertForbidden();
    }

    public function test_erasing_the_patient_removes_their_requests_and_bookings(): void
    {
        [$laboratory] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();
        $this->request($patient);
        $this->actingAs($patient)->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))->assertCreated();

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertDatabaseCount('lab_bookings', 0);
        $this->assertDatabaseCount('lab_requests', 0);
    }
}
