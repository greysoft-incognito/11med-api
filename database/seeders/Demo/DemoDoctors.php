<?php

namespace Database\Seeders\Demo;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\DoctorDocument;
use App\Models\DoctorProfile;
use App\Models\User;

/**
 * The app mock's doctors (`seedCatalogue.ts`, `seed.ts`): approved, listed, with fees that
 * differ by doctor and session, one unavailable now, one without ratings, one without hours.
 */
class DemoDoctors
{
    public function __construct(
        private readonly DemoPictures $pictures,
    ) {}

    /**
     * @return array<string, array<string, mixed>> keyed by phone number
     */
    public static function directory(): array
    {
        $weekdays = fn(array $days, string $start, string $end) => array_map(
            fn(int $day) => ['day' => $day, 'start' => $start, 'end' => $end],
            $days
        );

        return [
            '08030000011' => ['full_name' => 'Adaeze Nwosu', 'specialty' => 'general_practice', 'sub_specialty' => null, 'years_of_experience' => 9, 'current_institution' => 'Lagos University Teaching Hospital', 'languages' => ['english', 'igbo'], 'bio' => 'General practice with a focus on hypertension and diabetes management. I see a lot of patients who have been on the same prescription for years without review.', 'photo' => '1651008376811-b90baee60c1f', 'fees' => [250_000, 400_000, 550_000], 'rating' => [4.8, 126], 'availability' => $weekdays([1, 2, 3, 4, 5, 6], '08:00', '12:00'), 'session_minutes' => 15, 'chat_window_hours' => 24, 'follow_up_fee_kobo' => 100_000, 'is_available_now' => true],
            '08030000012' => ['full_name' => 'Ibrahim Suleiman', 'specialty' => 'cardiology', 'sub_specialty' => 'Interventional cardiology', 'years_of_experience' => 15, 'current_institution' => 'Ahmadu Bello University Teaching Hospital', 'languages' => ['english', 'hausa'], 'bio' => 'Cardiologist. Most of what I treat starts as untreated high blood pressure, so I would rather see you early than after an event.', 'photo' => '1622253692010-333f2da6031d', 'fees' => [400_000, 650_000, 900_000], 'rating' => [4.9, 84], 'availability' => $weekdays([2, 4], '14:00', '18:00'), 'session_minutes' => 30, 'chat_window_hours' => 24, 'follow_up_fee_kobo' => null, 'is_available_now' => false],
            '08030000013' => ['full_name' => 'Folake Adeyemi', 'specialty' => 'paediatrics', 'sub_specialty' => null, 'years_of_experience' => 6, 'current_institution' => 'University College Hospital, Ibadan', 'languages' => ['english', 'yoruba'], 'bio' => 'Paediatrics. Happy to talk through a fever at 2am, and equally happy to tell you it can wait until morning.', 'photo' => '3786525', 'fees' => [200_000, 350_000, 500_000], 'rating' => null, 'availability' => [...$weekdays([1, 2, 3, 4, 5], '18:00', '21:00'), ['day' => 6, 'start' => '09:00', 'end' => '12:00']], 'session_minutes' => 20, 'chat_window_hours' => 48, 'follow_up_fee_kobo' => 100_000, 'is_available_now' => true],
            '08030000014' => ['full_name' => 'Chinedu Okoro', 'specialty' => 'endocrinology', 'sub_specialty' => 'Diabetes', 'years_of_experience' => 12, 'current_institution' => 'National Hospital Abuja', 'languages' => ['english', 'igbo', 'hausa'], 'bio' => 'Endocrinology, mostly type 2 diabetes. Bring your recent readings if you have them; if you do not, we will start there.', 'photo' => '1181391', 'fees' => [350_000, 500_000, 700_000], 'rating' => [4.6, 41], 'availability' => $weekdays([1, 3, 5], '10:00', '14:00'), 'session_minutes' => 30, 'chat_window_hours' => 24, 'follow_up_fee_kobo' => 250_000, 'is_available_now' => true],
            '08030000015' => ['full_name' => 'Aisha Bello', 'specialty' => 'obstetrics', 'sub_specialty' => null, 'years_of_experience' => 11, 'current_institution' => 'Aminu Kano Teaching Hospital', 'languages' => ['english', 'hausa'], 'bio' => 'Obstetrics and antenatal care. Questions you think are too small to ask are usually the ones worth asking.', 'photo' => '1181519', 'fees' => [300_000, 450_000, 600_000], 'rating' => [4.7, 58], 'availability' => [], 'session_minutes' => 45, 'chat_window_hours' => 12, 'follow_up_fee_kobo' => null, 'is_available_now' => true],
        ];
    }

    /**
     * An approved doctor account with every required document accepted.
     *
     * @param  array<string, mixed>  $doctor  One entry of `directory()`, plus `mdcn_number`, `email` and `bank_name`
     */
    public function create(string $phone, array $doctor): User
    {
        $user = DemoAccounts::create($doctor['full_name'], $phone, $doctor['email'] ?? null, Role::Doctor);
        $reviewedAt = now()->subDays(28);
        [$chat, $audio, $video] = $doctor['fees'];
        [$mean, $count] = $doctor['rating'] ?? [0, 0];

        $profile = new DoctorProfile([
            'user_id' => $user->id,
            'mdcn_number' => $doctor['mdcn_number'] ?? 'MDCN-' . substr($phone, -5),
            'specialty' => $doctor['specialty'],
            'sub_specialty' => $doctor['sub_specialty'],
            'years_of_experience' => $doctor['years_of_experience'],
            'current_institution' => $doctor['current_institution'],
            'languages' => $doctor['languages'],
            'bio' => $doctor['bio'],
            'fee_chat_kobo' => $chat,
            'fee_audio_kobo' => $audio,
            'fee_video_kobo' => $video,
            'session_minutes' => $doctor['session_minutes'],
            'chat_window_hours' => $doctor['chat_window_hours'],
            'follow_up_fee_kobo' => $doctor['follow_up_fee_kobo'],
            'bank_name' => $doctor['bank_name'] ?? 'Access Bank',
            'availability' => $doctor['availability'],
            'is_available_now' => $doctor['is_available_now'],
        ]);

        $profile->forceFill([
            'photo_media_id' => $this->pictures->store($user, MediaPurpose::DoctorPhoto, DemoPictures::portrait($doctor['photo']))?->id,
            'bank_account_number' => '01234' . substr($phone, -5),
            'bank_account_last4' => substr($phone, -4),
            'verification_status' => VerificationStatus::Approved,
            'submitted_at' => now()->subDays(30),
            'reviewed_at' => $reviewedAt,
            'rating_count' => $count,
            'rating_total' => (int) round($mean * $count),
            'rating_mean' => $mean,
        ]);
        $profile->save();

        foreach (DocumentKind::cases() as $kind) {
            if ($kind === DocumentKind::SpecialistCertificate && $doctor['sub_specialty'] === null) {
                continue;
            }

            DoctorDocument::create([
                'doctor_profile_id' => $profile->id,
                'kind' => $kind,
                'status' => DocumentStatus::Accepted,
                'uploaded_at' => now()->subDays(30),
                'reviewed_at' => $reviewedAt,
            ]);
        }

        return $user;
    }
}
