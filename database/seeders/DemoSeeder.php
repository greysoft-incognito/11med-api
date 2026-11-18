<?php

namespace Database\Seeders;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use Database\Seeders\Demo\DemoDoctors;
use Database\Seeders\Demo\DemoLaboratories;
use Database\Seeders\Demo\DemoPatient;
use Database\Seeders\Demo\DemoPharmacies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The app mock's demo data on a real database, so the app in live mode opens on the same
 * screens it shows against its mock: the demo patient (08030000001) and doctor (08030000003),
 * both `Password1`, the doctor directory, five pharmacies and five laboratories whose portal
 * accounts are `<name-slug>@example.com` with the same password.
 *
 * Runs once: `php artisan db:seed --class=DemoSeeder --force`. Does nothing when the demo
 * patient already exists. Pictures are fetched from Unsplash and Pexels and left out when
 * they cannot be.
 */
class DemoSeeder extends Seeder
{
    public const DOCTOR_PHONE = '08030000003';

    public function run(DemoPatient $patients, DemoDoctors $doctors, DemoPharmacies $pharmacies, DemoLaboratories $laboratories): void
    {
        if (User::query()->where('phone', DemoPatient::PHONE)->exists()) {
            $this->command?->warn('The demo patient exists; nothing seeded.');

            return;
        }

        $this->call(LabCatalogueSeeder::class);

        DB::transaction(function () use ($patients, $doctors, $pharmacies, $laboratories) {
            foreach (DemoDoctors::directory() as $phone => $doctor) {
                $doctors->create($phone, $doctor);
            }

            $patient = $patients->create();
            $doctor = $doctors->create(self::DOCTOR_PHONE, $this->tunde());
            $this->consultations($patient, $doctor);

            $pharmacies->create();
            $laboratories->create();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function tunde(): array
    {
        return [
            'full_name' => 'Tunde Bakare',
            'email' => 'tunde@example.com',
            'mdcn_number' => 'MDCN-48217',
            'specialty' => 'general_practice',
            'sub_specialty' => null,
            'years_of_experience' => 11,
            'current_institution' => 'Lagos State University Teaching Hospital',
            'languages' => ['english', 'yoruba'],
            'bio' => 'General practice, mostly hypertension and diabetes follow-up. I would rather talk through what a patient is already taking than add to it.',
            'photo' => '6626903',
            'fees' => [300_000, 450_000, 600_000],
            'rating' => [4.8, 23],
            'session_minutes' => 20,
            'chat_window_hours' => 24,
            'follow_up_fee_kobo' => 150_000,
            'bank_name' => 'Guaranty Trust Bank',
            'availability' => [
                ['day' => 1, 'start' => '09:00', 'end' => '17:00'],
                ['day' => 3, 'start' => '09:00', 'end' => '17:00'],
                ['day' => 5, 'start' => '09:00', 'end' => '13:00'],
            ],
            'is_available_now' => true,
        ];
    }

    /**
     * Two finished, paid chat sessions with the demo patient, so the doctor's earnings and the
     * patient's history have something to show; the second is left unrated for the prompt.
     */
    private function consultations(User $patient, User $doctor): void
    {
        $sessions = [
            [9, 'Blood pressure readings have been higher than usual for about two weeks.', 5],
            [2, 'Follow-up on the blood pressure, and a question about taking both tablets together.', null],
        ];

        foreach ($sessions as [$daysAgo, $complaint, $rating]) {
            $at = now()->subDays($daysAgo);

            $consultation = new Consultation([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'doctor_name' => 'Tunde Bakare',
                'doctor_specialty' => 'general_practice',
                'session_type' => 'chat',
                'status' => ConsultationStatus::Completed,
                'complaint' => $complaint,
                'fee_kobo' => 300_000,
                'scheduled_for' => $at,
                'scheduled' => false,
                'length_minutes' => 1440,
                'paid_at' => $at,
                'started_at' => $at,
                'ended_at' => $at->copy()->addDay(),
                'profile_shared' => true,
            ]);
            $consultation->forceFill(['rating' => $rating, 'rated_at' => $rating === null ? null : $at->copy()->addDay()]);
            $consultation->created_at = $at;
            $consultation->save();
        }
    }
}
