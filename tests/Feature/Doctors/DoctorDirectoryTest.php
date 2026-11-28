<?php

namespace Tests\Feature\Doctors;

use App\Enums\AccountKind;
use App\Models\User;
use App\Services\Doctors\DoctorRatings;
use App\Services\Doctors\EngagedDoctors;
use App\Services\Doctors\HeldSlots;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Tests\TestCase;

class DoctorDirectoryTest extends TestCase
{
    use MakesDoctors;

    public function test_only_approved_doctors_are_listed_available_first_then_by_rating(): void
    {
        $patient = $this->user(AccountKind::Patient);
        $unrated = $this->approvedDoctor(['is_available_now' => true]);
        $rated = $this->approvedDoctor(['is_available_now' => true]);
        $away = $this->approvedDoctor();
        $this->submittedDoctor();

        app(DoctorRatings::class)->record($rated->id, 5);
        app(DoctorRatings::class)->record($rated->id, 4);
        app(DoctorRatings::class)->record($away->id, 5);

        $this->actingAs($patient)
            ->getJson('api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.0.id', $rated->uuid)
            ->assertJsonPath('data.items.0.rating', 4.5)
            ->assertJsonPath('data.items.0.rating_count', 2)
            ->assertJsonPath('data.items.1.id', $unrated->uuid)
            ->assertJsonPath('data.items.1.rating', null)
            ->assertJsonPath('data.items.2.id', $away->uuid)
            ->assertJsonPath('data.items.2.is_available_now', false)
            ->assertJsonMissingPath('data.items.0.bank_name')
            ->assertJsonMissingPath('data.items.0.documents')
            ->assertJsonMissingPath('data.items.0.mdcn_number');

        $first = $this->actingAs($patient)
            ->getJson('api/v1/doctors?limit=2')
            ->json('data');

        $this->actingAs($patient)
            ->getJson('api/v1/doctors?limit=2&cursor=' . urlencode($first['next_cursor']))
            ->assertJsonPath('data.items.0.id', $away->uuid)
            ->assertJsonPath('data.next_cursor', null);
    }

    public function test_the_directory_filters(): void
    {
        $patient = $this->user(AccountKind::Patient);
        $cardiologist = $this->approvedDoctor(['is_available_now' => true]);
        $gp = $this->approvedDoctor(['specialty' => 'general_practice', 'languages' => ['hausa']]);

        $cases = [
            'specialty=general_practice' => [$gp],
            'language=yoruba' => [$cardiologist],
            'available_now=true' => [$cardiologist],
            'q=general%20pract' => [$gp],
            'q=' . urlencode(mb_substr($gp->full_name, 0, 5)) => [$gp],
        ];

        foreach ($cases as $query => $expected) {
            $ids = $this->actingAs($patient)
                ->getJson("api/v1/doctors?{$query}")
                ->assertOk()
                ->json('data.items.*.id');

            $this->assertSame(array_map(fn(User $d) => $d->uuid, $expected), $ids, $query);
        }
    }

    public function test_a_doctor_the_patient_is_with_is_not_offered_as_available(): void
    {
        $patient = $this->user(AccountKind::Patient);
        $doctor = $this->approvedDoctor(['is_available_now' => true]);

        $this->app->instance(EngagedDoctors::class, new class($doctor->id) implements EngagedDoctors
        {
            public function __construct(private int $id) {}

            public function for(User $patient): array
            {
                return [$this->id];
            }
        });

        $this->actingAs($patient)
            ->getJson("api/v1/doctors/{$doctor->uuid}")
            ->assertOk()
            ->assertJsonPath('data.is_available_now', false);

        $this->actingAs($patient)
            ->getJson('api/v1/doctors?available_now=true')
            ->assertJsonPath('data.total', 0);
    }

    public function test_an_unlisted_doctor_is_not_found(): void
    {
        $patient = $this->user(AccountKind::Patient);
        $pending = $this->submittedDoctor();
        $suspended = $this->approvedDoctor();

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/doctors/{$suspended->uuid}/review", ['decision' => 'suspended', 'notes' => 'Licence lapsed with the MDCN.'])
            ->assertOk();

        foreach ([$pending, $suspended, $patient] as $user) {
            foreach (["api/v1/doctors/{$user->uuid}", "api/v1/doctors/{$user->uuid}/slots"] as $route) {
                $this->actingAs($patient)
                    ->getJson($route)
                    ->assertNotFound()
                    ->assertJsonPath('error.code', 'DOCTOR_NOT_FOUND');
            }
        }
    }

    public function test_portal_accounts_do_not_browse_the_directory(): void
    {
        foreach ([$this->admin(), $this->user(AccountKind::Pharmacy)] as $account) {
            $this->actingAs($account)
                ->getJson('api/v1/doctors')
                ->assertForbidden();
        }
    }

    public function test_slots_are_cut_from_the_hours_in_lagos_less_what_is_held(): void
    {
        // Monday 2026-11-23, 07:00 in Lagos.
        $this->travelTo(CarbonImmutable::parse('2026-11-23 06:00:00', 'UTC'));

        $patient = $this->user(AccountKind::Patient);
        $doctor = $this->approvedDoctor([
            'session_minutes' => 45,
            'availability' => [['day' => 1, 'start' => '08:00', 'end' => '11:00']],
        ]);

        $this->app->instance(HeldSlots::class, new class implements HeldSlots
        {
            public function heldBetween(int $doctorUserId, CarbonInterface $from, CarbonInterface $to): array
            {
                return [CarbonImmutable::parse('2026-11-30 07:45:00', 'UTC')];
            }
        });

        // Today 08:00 and 08:45 fall inside the two-hour lead; next Monday 08:45 is held.
        $this->actingAs($patient)
            ->getJson("api/v1/doctors/{$doctor->uuid}/slots")
            ->assertOk()
            ->assertJsonPath('data.has_schedule', true)
            ->assertJsonPath('data.slot_minutes', 45)
            ->assertJsonPath('data.slots', [
                ['starts_at' => '2026-11-23T08:30:00.000Z', 'ends_at' => '2026-11-23T09:15:00.000Z'],
                ['starts_at' => '2026-11-23T09:15:00.000Z', 'ends_at' => '2026-11-23T10:00:00.000Z'],
                ['starts_at' => '2026-11-30T07:00:00.000Z', 'ends_at' => '2026-11-30T07:45:00.000Z'],
                ['starts_at' => '2026-11-30T08:30:00.000Z', 'ends_at' => '2026-11-30T09:15:00.000Z'],
                ['starts_at' => '2026-11-30T09:15:00.000Z', 'ends_at' => '2026-11-30T10:00:00.000Z'],
            ]);
    }

    public function test_a_doctor_without_hours_has_no_schedule(): void
    {
        $doctor = $this->approvedDoctor();

        $this->actingAs($this->user(AccountKind::Patient))
            ->getJson("api/v1/doctors/{$doctor->uuid}/slots")
            ->assertOk()
            ->assertJsonPath('data.has_schedule', false)
            ->assertJsonPath('data.slots', []);
    }
}
