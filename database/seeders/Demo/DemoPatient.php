<?php

namespace Database\Seeders\Demo;

use App\Enums\DoseState;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Enums\SkipReason;
use App\Models\DoseLog;
use App\Models\MedicalProfile;
use App\Models\Medication;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The app mock's demo patient (`seed.ts`): a full profile, two chronic medications and one
 * finished course, fourteen days of answered doses ending yesterday, and a month of readings.
 */
class DemoPatient
{
    public const PHONE = '08030000001';

    public function __construct(
        private readonly DemoPictures $pictures,
    ) {}

    public function create(): User
    {
        $user = DemoAccounts::create('Amara Okafor', self::PHONE, 'amara@example.com', Role::Patient);

        $this->profile($user);
        $this->medications($user);
        DemoReadings::create($user);

        return $user;
    }

    private function profile(User $user): void
    {
        MedicalProfile::create([
            'user_id' => $user->id,
            'date_of_birth' => '1968-04-12',
            'gender' => 'female',
            'blood_type' => 'O+',
            'genotype' => 'AA',
            'height_cm' => 164,
            'weight_kg' => 71,
            'state_of_residence' => 'Lagos',
            'drug_allergies' => [['drug_name' => 'Penicillin', 'severity' => 'severe', 'reaction_type' => 'Swelling and rash']],
            'existing_conditions' => ['Hypertension', 'Type 2 diabetes'],
            'past_surgeries' => [],
            'food_allergies' => [],
            'contact_name' => 'Chidi Okafor',
            'contact_phone' => '08030000002',
            'contact_relationship' => 'spouse',
            'contact_verified_at' => now()->subDays(30),
            'hmo_provider' => 'Hygeia HMO',
            'hmo_member_id' => 'HYG-4471290',
            'photo_media_id' => $this->pictures->store($user, MediaPurpose::ProfilePhoto, DemoPictures::portrait('17977420'))?->id,
        ]);
    }

    private function medications(User $user): void
    {
        $today = CarbonImmutable::today(config('elevenmed.timezone'));
        $schedule = [
            'dosage_form' => 'tablet',
            'dose_quantity' => 1,
            'frequency_type' => 'daily',
            'start_date' => $today->subDays(60),
            'with_food' => 'not_specified',
            'reminder_active' => true,
            'caregiver_alerts' => false,
        ];

        $amlodipine = $this->medication($user, [...$schedule, 'drug_name' => 'Amlodipine', 'dosage_strength' => '10mg', 'specific_times' => ['08:00'], 'condition_being_treated' => 'Hypertension', 'refill_reminder_days' => 7, 'estimated_supply_units' => 24]);
        $metformin = $this->medication($user, [...$schedule, 'drug_name' => 'Metformin', 'brand_name' => 'Glucophage', 'dosage_strength' => '500mg', 'frequency_type' => 'twice_daily', 'specific_times' => ['08:00', '20:00'], 'with_food' => 'with_food', 'special_instructions' => 'Take with a meal to reduce stomach upset.', 'condition_being_treated' => 'Type 2 diabetes']);
        $this->medication($user, [...$schedule, 'drug_name' => 'Amoxicillin', 'dosage_strength' => '500mg', 'dosage_form' => 'capsule', 'frequency_type' => 'three_times_daily', 'specific_times' => ['08:00', '14:00', '20:00'], 'start_date' => $today->subDays(20), 'end_date' => $today->subDays(13), 'reminder_active' => false, 'condition_being_treated' => 'Chest infection']);

        foreach ([$amlodipine, $metformin] as $medication) {
            $this->doses($user, $medication, $today);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function medication(User $user, array $fields): Medication
    {
        $medication = new Medication(['user_id' => $user->id, ...$fields]);
        $medication->created_at = $fields['start_date'];
        $medication->settled_through = CarbonImmutable::today(config('elevenmed.timezone'))->utc();
        $medication->save();

        return $medication;
    }

    /**
     * The mock's fixed pattern: one bad day, one skip, two late doses, the rest taken. Today
     * is left to the patient, and to the missed-dose job.
     */
    private function doses(User $user, Medication $medication, CarbonImmutable $today): void
    {
        $evening = fn(string $time) => $time === '20:00';

        for ($offset = 14; $offset >= 1; $offset--) {
            $day = $today->subDays($offset);

            foreach ($medication->specific_times as $time) {
                $state = match (true) {
                    $offset === 9, $offset === 6 && $evening($time) => DoseState::Missed,
                    $offset === 4 && $evening($time), $offset === 11 && $time === '08:00' => DoseState::TakenLate,
                    $offset === 3 && $medication->drug_name === 'Metformin' && $evening($time) => DoseState::Skipped,
                    default => DoseState::Taken,
                };
                $scheduledFor = CarbonImmutable::parse("{$day->toDateString()} {$time}", config('elevenmed.timezone'))->utc();

                DoseLog::create([
                    'user_id' => $user->id,
                    'medication_id' => $medication->id,
                    'scheduled_for' => $scheduledFor,
                    'state' => $state,
                    'skip_reason' => $state === DoseState::Skipped ? SkipReason::RanOut : null,
                    'logged_at' => $state === DoseState::Missed ? null : $scheduledFor->addMinutes($state === DoseState::TakenLate ? 90 : 5),
                    'snooze_count' => 0,
                ]);
            }
        }
    }
}
