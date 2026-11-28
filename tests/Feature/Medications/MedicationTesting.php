<?php

namespace Tests\Feature\Medications;

use App\Enums\AccountKind;
use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\User;
use App\Services\Subscriptions\Entitlements;

/**
 * Patients, schedules and premium for the medication and reading tests.
 */
trait MedicationTesting
{
    protected function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }

    protected function doctor(): User
    {
        return User::factory()
            ->app(AccountKind::Doctor)
            ->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function schedule(User $patient, array $overrides = []): array
    {
        return $this->actingAs($patient)
            ->postJson('api/v1/medications', [
                'drug_name' => 'Amlodipine',
                'dosage_strength' => '5mg',
                'dosage_form' => 'tablet',
                'dose_quantity' => 1,
                'frequency_type' => 'twice_daily',
                'specific_times' => ['08:00', '20:00'],
                'start_date' => '2026-11-10',
                'reminder_active' => true,
                ...$overrides,
            ])
            ->assertCreated()
            ->json('data');
    }

    protected function premium(): void
    {
        $this->app->instance(Entitlements::class, new class extends Entitlements
        {
            public function medicationLimit(User $user): ?int
            {
                return null;
            }

            public function historyDays(User $user): ?int
            {
                return null;
            }

            public function readingsDays(User $user): ?int
            {
                return null;
            }

            public function requirePremium(User $user): void {}
        });
    }

    protected function careFor(User $patient, User $carer): void
    {
        Caregiver::create([
            'patient_id' => $patient->id,
            'phone' => '08031112222',
            'invite_code' => 'ABC234',
            'status' => CaregiverStatus::Active,
            'caregiver_user_id' => $carer->id,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    protected function doseId(string $medicationId, string $instant): string
    {
        return $medicationId . ':' . $instant;
    }
}
