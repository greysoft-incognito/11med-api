<?php

namespace Tests\Feature\Profile;

use App\Enums\Role;
use App\Models\QrAccess;
use App\Models\User;
use Tests\TestCase;

class QrTest extends TestCase
{
    public function test_the_owner_gets_one_lasting_code_and_others_are_told_it_does_not_exist(): void
    {
        $patient = $this->patient();

        $first = $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->assertOk()
            ->assertJsonPath('data.last_accessed_at', null)
            ->assertJsonStructure(['data' => ['token', 'url', 'issued_at']])
            ->json('data');

        $this->assertSame('https://11med.ng/profile/view?token=' . $first['token'], $first['url']);

        $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->assertJsonPath('data.token', $first['token']);

        $this->actingAs($this->patient())
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_the_page_shows_the_safe_subset_and_counts_each_open(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/profile', [
                ...ProfileTest::CORE,
                'date_of_birth' => now('Africa/Lagos')->subYears(34)->toDateString(),
            ])
            ->assertCreated();

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['existing_conditions' => ['HIV'], 'hmo' => ['member_id' => 'H-1']])
            ->assertOk();

        $token = $this->tokenOf($patient);

        $this->app['auth']->forgetGuards();

        $page = $this->getJson('api/v1/profile/view?token=' . $token)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.name', $patient->full_name)
            ->assertJsonPath('data.age', 34)
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.genotype', 'AS')
            ->assertJsonPath('data.drug_allergies.0', ['drug_name' => 'Penicillin', 'reaction' => null, 'severity' => 'severe'])
            ->assertJsonPath('data.medications', [])
            ->assertJsonPath('data.emergency_contact', ['name' => 'Chidi Okafor', 'phone' => '08035550100', 'relationship' => 'spouse'])
            ->assertJsonPath('data.opened_count', 1)
            ->json('data');

        $this->assertSame(
            ['name', 'age', 'gender', 'blood_type', 'genotype', 'drug_allergies', 'medications', 'emergency_contact', 'issued_at', 'opened_count'],
            array_keys($page)
        );

        $this->getJson('api/v1/profile/view?token=' . $token)->assertJsonPath('data.opened_count', 2);

        $this->assertSame(2, QrAccess::count());

        $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->assertJsonPath('data.last_accessed_at', fn(?string $at) => $at !== null);
    }

    public function test_a_code_without_a_profile_still_opens_with_what_there_is(): void
    {
        $patient = $this->patient();
        $token = $this->tokenOf($patient);

        $this->getJson('api/v1/profile/view?token=' . $token)
            ->assertOk()
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.drug_allergies', [])
            ->assertJsonPath('data.emergency_contact', null);
    }

    public function test_revoking_replaces_the_code_and_the_old_one_stops_at_once(): void
    {
        $patient = $this->patient();
        $old = $this->tokenOf($patient);

        $new = $this->actingAs($patient)
            ->postJson('api/v1/profile/qr/revoke')
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($old, $new);

        $this->getJson('api/v1/profile/view?token=' . $old)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'QR_NOT_FOUND');

        $this->getJson('api/v1/profile/view?token=' . $new)->assertOk();
        $this->getJson('api/v1/profile/view?token=never-issued')->assertNotFound();
    }

    public function test_the_page_is_limited_per_token(): void
    {
        $token = $this->tokenOf($this->patient());

        foreach (range(1, config('elevenmed.emergency.opens_per_hour_per_token')) as $ignored) {
            $this->getJson('api/v1/profile/view?token=' . $token)->assertOk();
        }

        $this->getJson('api/v1/profile/view?token=' . $token)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    private function tokenOf(User $patient): string
    {
        return $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->json('data.token');
    }

    private function patient(): User
    {
        return User::factory()
            ->app(Role::Patient)
            ->create();
    }
}
