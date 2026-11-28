<?php

namespace Tests\Feature\Laboratories;

use App\Enums\AccountKind;
use App\Models\LabTest;
use App\Models\User;
use Database\Seeders\LabCatalogueSeeder;
use Tests\Concerns\MakesLaboratories;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use MakesLaboratories;

    private function body(array $overrides = []): array
    {
        return [
            'code' => 'HBA1C',
            'name' => 'HbA1c',
            'category' => 'chemistry',
            'sample' => 'blood',
            'description' => 'Average blood sugar over the last three months.',
            'fasting_hours' => 0,
            'turnaround_hours' => 24,
            ...$overrides,
        ];
    }

    public function test_the_team_adds_lists_and_edits_a_test(): void
    {
        $admin = $this->admin();
        $this->labTest('FBC', ['name' => 'Full blood count']);

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/catalogue', $this->body(['code' => ' HBA1C ']))
            ->assertCreated()
            ->assertJsonPath('data.code', 'HBA1C')
            ->assertJsonPath('data.turnaround_hours', 24);

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/catalogue?q=blood')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'FBC')
            ->assertJsonPath('data.1.code', 'HBA1C');

        $this->actingAs($admin)
            ->getJson('api/v1/portal/admin/catalogue?q=hba')
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->putJson('api/v1/portal/admin/catalogue/hba1c', $this->body(['name' => 'Haemoglobin A1c', 'fasting_hours' => 8]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Haemoglobin A1c')
            ->assertJsonPath('data.fasting_hours', 8);
    }

    public function test_a_price_list_follows_a_catalogue_edit(): void
    {
        [, $account] = $this->laboratory(['HBA1C' => 500000]);

        $this->actingAs($this->admin())
            ->putJson('api/v1/portal/admin/catalogue/HBA1C', $this->body(['name' => 'Haemoglobin A1c']))
            ->assertOk();

        $this->actingAs($account)
            ->getJson('api/v1/portal/laboratory/tests')
            ->assertJsonPath('data.0.name', 'Haemoglobin A1c')
            ->assertJsonPath('data.0.price_kobo', 500000);
    }

    public function test_refusals(): void
    {
        $admin = $this->admin();
        $this->labTest('HBA1C');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/catalogue', $this->body())
            ->assertConflict()
            ->assertJsonPath('error.code', 'DUPLICATE_CODE')
            ->assertJsonPath('error.field', 'code');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/catalogue', $this->body(['code' => 'hb a1c']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'code');

        $this->actingAs($admin)
            ->postJson('api/v1/portal/admin/catalogue', $this->body(['code' => 'NEW', 'description' => 'Short']))
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'description');

        $this->actingAs($admin)
            ->putJson('api/v1/portal/admin/catalogue/HBA1C', $this->body(['code' => 'A1C']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CODE_IMMUTABLE')
            ->assertJsonPath('error.field', 'code');

        $this->actingAs($admin)
            ->putJson('api/v1/portal/admin/catalogue/NOPE', $this->body(['code' => 'NOPE']))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TEST_NOT_FOUND');
    }

    public function test_the_starting_catalogue_is_seeded_once_and_keeps_the_teams_edits(): void
    {
        $this->seed(LabCatalogueSeeder::class);

        LabTest::query()->where('code', 'FBC')->update(['name' => 'FBC with differential']);

        $this->seed(LabCatalogueSeeder::class);

        $this->assertSame(20, LabTest::query()->count());
        $this->assertSame('FBC with differential', LabTest::query()->where('code', 'FBC')->value('name'));
    }

    public function test_only_the_team_maintains_it(): void
    {
        [, $laboratory] = $this->laboratory();
        $patient = User::factory()->app(AccountKind::Patient)->create();

        $this->getJson('api/v1/portal/admin/catalogue')->assertUnauthorized();

        foreach ([$laboratory, $patient] as $account) {
            $this->actingAs($account)
                ->postJson('api/v1/portal/admin/catalogue', $this->body())
                ->assertForbidden();
        }
    }
}
