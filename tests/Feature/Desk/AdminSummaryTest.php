<?php

namespace Tests\Feature\Desk;

use App\Enums\Role;
use App\Services\Partners\Organisations;
use App\Services\Subscriptions\Subscriptions;
use Tests\Concerns\MakesPartners;
use Tests\Feature\Consultations\MakesConsultations;
use Tests\TestCase;

class AdminSummaryTest extends TestCase
{
    use MakesConsultations;
    use MakesPartners;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSeams();
    }

    public function test_an_empty_desk_reads_zero_with_no_oldest_wait(): void
    {
        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/admin/summary')
            ->assertOk()
            ->assertJsonPath('data', [
                'doctors_awaiting_review' => 0,
                'doctors_oldest_waiting_hours' => null,
                'partners_awaiting_review' => 0,
                'payouts_pending' => 0,
                'payouts_pending_kobo' => 0,
                'premium_active' => 0,
            ]);
    }

    public function test_the_desk_counts_what_waits_on_the_team(): void
    {
        $this->travelTo(now()->subHours(5));
        $this->submittedDoctor();
        $this->travelBack();

        $this->submittedDoctor();
        $this->completeDoctor();

        $organisations = app(Organisations::class);
        $organisations->apply($this->application());
        $this->approvedPartner();

        $doctor = $this->availableDoctor();
        $id = $this->openSession($this->patient(), $doctor);
        $this->doctorDoes($doctor, $id, 'end');
        $this->artisan('consultations:payouts')->assertSuccessful();

        $admin = $this->admin();
        $premium = $this->patient();
        $lapsed = $this->patient();
        $subscriptions = app(Subscriptions::class);
        $subscriptions->record($admin, $premium->phone, now('Africa/Lagos')->addMonth()->toDateString(), 'PSK-SUMMARY-1');
        $subscriptions->record($admin, $lapsed->phone, now('Africa/Lagos')->addDay()->toDateString(), 'PSK-SUMMARY-2');

        $this->travel(3)->days();

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/summary')
            ->assertOk()
            ->assertJsonPath('data.doctors_awaiting_review', 2)
            ->assertJsonPath('data.doctors_oldest_waiting_hours', 77)
            ->assertJsonPath('data.partners_awaiting_review', 1)
            ->assertJsonPath('data.payouts_pending', 1)
            ->assertJsonPath('data.payouts_pending_kobo', 425000)
            ->assertJsonPath('data.premium_active', 1);
    }

    public function test_only_the_team_reads_the_desk(): void
    {
        $this->getJson('api/v1/portal/admin/summary')->assertUnauthorized();

        foreach ([Role::Patient, Role::Doctor, Role::Pharmacy, Role::Laboratory] as $role) {
            $this->actingAs($this->user($role))
                ->getJson('api/v1/portal/admin/summary')
                ->assertForbidden();
        }
    }
}
