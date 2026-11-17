<?php

namespace Tests\Concerns;

use App\Enums\PartnerKind;
use App\Enums\Role;
use App\Models\Laboratory;
use App\Models\LaboratoryTest;
use App\Models\LabTest;
use App\Models\MedicalProfile;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Payments\Payments;
use Tests\Fakes\FakePayments;
use Tests\Fakes\RecordingNotifier;

/**
 * Laboratories for feature tests: catalogue tests, approved laboratories with price lists,
 * patients, and the payment and notification fakes.
 */
trait MakesLaboratories
{
    use MakesPartners;

    protected FakePayments $payments;

    protected RecordingNotifier $notifier;

    protected function fakeLabSeams(): void
    {
        $this->payments = new FakePayments;
        $this->notifier = new RecordingNotifier;

        $this->app->instance(Payments::class, $this->payments);
        $this->app->instance(Notifier::class, $this->notifier);
    }

    protected function labTest(string $code, array $overrides = []): LabTest
    {
        return LabTest::create([
            'code' => $code,
            'name' => $overrides['name'] ?? "Test {$code}",
            'category' => 'haematology',
            'sample' => 'blood',
            'description' => 'Looks at the cells in the blood.',
            'fasting_hours' => 0,
            'turnaround_hours' => 24,
            ...$overrides,
        ]);
    }

    /**
     * An approved laboratory, its account, and its price list from `$prices` (code => kobo, or
     * code => [kobo, available]).
     *
     * @param  array<string, int|array{0: int, 1: bool}>  $prices
     * @param  array<string, mixed>  $overrides
     * @return array{0: Laboratory, 1: User}
     */
    protected function laboratory(array $prices = [], array $overrides = [], array $listing = []): array
    {
        [$organisation, $account] = $this->approvedPartner(PartnerKind::Laboratory, [
            'name' => 'Lab ' . fake()->unique()->word(),
            'licence_number' => 'MLSCN-' . fake()->unique()->numerify('######'),
            ...$overrides,
        ]);

        $laboratory = Laboratory::query()->where('organisation_id', $organisation->id)->firstOrFail();
        $laboratory->update($listing);

        foreach ($prices as $code => $price) {
            [$kobo, $available] = is_array($price) ? $price : [$price, true];

            LaboratoryTest::create([
                'laboratory_id' => $laboratory->id,
                'lab_test_id' => LabTest::query()->where('code', $code)->value('id') ?? $this->labTest($code)->id,
                'price_kobo' => $kobo,
                'available' => $available,
            ]);
        }

        return [$laboratory->refresh(), $account];
    }

    protected function labPatient(?string $state = null): User
    {
        $patient = User::factory()
            ->app(Role::Patient)
            ->create();

        if ($state !== null) {
            MedicalProfile::create([
                'user_id' => $patient->id,
                'date_of_birth' => '1990-01-01',
                'gender' => 'female',
                'blood_type' => 'O+',
                'genotype' => 'AA',
                'state_of_residence' => $state,
                'contact_name' => 'Ada',
                'contact_phone' => '08031112222',
                'contact_relationship' => 'sibling',
            ]);
        }

        return $patient;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function bookingBody(Laboratory $laboratory, array $codes, array $overrides = []): array
    {
        return [
            'laboratory_id' => $laboratory->organisation->uuid,
            'test_codes' => $codes,
            'collection' => 'visit',
            'scheduled_for' => now()->addDay()->utc()->toIso8601ZuluString('millisecond'),
            ...$overrides,
        ];
    }
}
