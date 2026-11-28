<?php

namespace Tests\Feature\Caregiving;

use App\Enums\AccountKind;
use App\Events\Caregiving\CaregiverAccepted;
use App\Models\Caregiver;
use App\Models\User;
use App\Notifications\Caregiving\CaregiverInvite;
use App\Services\Caregiving\Caregivers;
use App\Services\Identity\AccountEraser;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CaregiverTest extends TestCase
{
    public function test_a_patient_invites_a_number_which_is_texted_the_code(): void
    {
        Notification::fake();

        $patient = $this->patient();

        $invite = $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/invite', ['phone' => '+2348031112222'])
            ->assertCreated()
            ->assertJsonPath('data.name', null)
            ->assertJsonPath('data.phone', '08031112222')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'invited_at', 'invite_code']])
            ->json('data');

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-RT-Z2-9]{6}$/', $invite['invite_code']);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            CaregiverInvite::class,
            fn(CaregiverInvite $sms, array $channels, AnonymousNotifiable $to) => str_contains($sms->smsText(), $invite['invite_code'])
                && in_array('+2348031112222', $to->routes, true)
        );

        $this->actingAs($patient)
            ->getJson('api/v1/medications/caregivers')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $invite['id'])
            ->assertJsonPath('data.next_cursor', null);
    }

    public function test_reinviting_is_a_duplicate_before_it_is_over_the_cap(): void
    {
        Notification::fake();

        $patient = $this->patient();

        $this->invite($patient, '08031112222');

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/invite', ['phone' => '+2348031112222'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'CAREGIVER_ALREADY_INVITED')
            ->assertJsonPath('error.field', 'phone');

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/invite', ['phone' => '08031113333'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CAREGIVER_LIMIT_REACHED')
            ->assertJsonPath('error.meta', ['limit' => 1, 'current' => 1]);

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/invite', ['phone' => '12345'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'phone');
    }

    public function test_the_invited_person_accepts_with_the_code_and_sees_the_patient(): void
    {
        Notification::fake();
        Event::fake([CaregiverAccepted::class]);

        $patient = $this->patient();
        $carer = $this->patient();
        $code = $this->invite($patient, '08031112222')['invite_code'];

        $this->actingAs($carer)
            ->postJson('api/v1/medications/caregivers/accept', ['code' => ' ' . strtolower($code)])
            ->assertOk()
            ->assertJsonPath('data.patient_id', $patient->uuid)
            ->assertJsonPath('data.patient_name', $patient->full_name)
            ->assertJsonStructure(['data' => ['since']]);

        Event::assertDispatched(CaregiverAccepted::class);

        $this->actingAs($carer)
            ->getJson('api/v1/medications/caregiving')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.patient_id', $patient->uuid);

        $this->actingAs($patient)
            ->getJson('api/v1/medications/caregivers')
            ->assertJsonPath('data.items.0.status', 'active')
            ->assertJsonPath('data.items.0.name', $carer->full_name);

        $this->assertTrue(app(Caregivers::class)->canRead($carer, $patient));
        $this->assertFalse(app(Caregivers::class)->canRead($patient, $carer));

        $this->actingAs($this->patient())
            ->postJson('api/v1/medications/caregivers/accept', ['code' => $code])
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVITE_ALREADY_USED')
            ->assertJsonPath('error.field', 'code');
    }

    public function test_a_code_that_was_never_issued_or_is_your_own_is_refused(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $code = $this->invite($patient, '08031112222')['invite_code'];

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/accept', ['code' => 'ZZZZZZ'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'INVITE_NOT_FOUND')
            ->assertJsonPath('error.field', 'code');

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/accept', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVITE_SELF');
    }

    public function test_revoking_takes_effect_at_once_and_only_the_patient_can(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $carer = $this->patient();
        $invite = $this->invite($patient, '08031112222');

        $this->actingAs($carer)->postJson('api/v1/medications/caregivers/accept', ['code' => $invite['invite_code']])->assertOk();

        $this->actingAs($carer)
            ->deleteJson('api/v1/medications/caregivers/' . $invite['id'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CAREGIVER_NOT_FOUND');

        $this->actingAs($patient)
            ->deleteJson('api/v1/medications/caregivers/' . $invite['id'])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertFalse(app(Caregivers::class)->canRead($carer, $patient));

        $this->actingAs($carer)
            ->getJson('api/v1/medications/caregiving')
            ->assertJsonPath('data.total', 0);

        $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/accept', ['code' => $invite['invite_code']])
            ->assertNotFound();

        $this->invite($patient, '08031112222');
    }

    public function test_erasing_either_side_clears_the_row_from_the_other(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $carer = $this->patient();
        $code = $this->invite($patient, '08031112222')['invite_code'];

        $this->actingAs($carer)->postJson('api/v1/medications/caregivers/accept', ['code' => $code])->assertOk();

        app(AccountEraser::class)->erase($carer, 'Password1', null);

        $this->assertSame(0, Caregiver::withTrashed()->count());
    }

    public function test_caregiving_is_for_patient_accounts(): void
    {
        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create();

        $this->actingAs($doctor)
            ->getJson('api/v1/medications/caregivers')
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function invite(User $patient, string $phone): array
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/medications/caregivers/invite', ['phone' => $phone])
            ->assertCreated()
            ->json('data');
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }
}
