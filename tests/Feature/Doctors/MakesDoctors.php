<?php

namespace Tests\Feature\Doctors;

use App\Enums\DocumentKind;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;

trait MakesDoctors
{
    public const PRACTICE = [
        'mdcn_number' => 'MDCN/12345',
        'specialty' => 'cardiology',
        'years_of_experience' => 9,
        'current_institution' => 'LUTH',
        'languages' => ['english', 'yoruba'],
        'bio' => 'Consultant cardiologist with a decade of practice in Lagos.',
        'fee_chat_kobo' => 500000,
        'fee_audio_kobo' => 750000,
        'fee_video_kobo' => 1000000,
        'bank_name' => 'GTBank',
        'bank_account_number' => '0123456789',
    ];

    protected function user(Role $role): User
    {
        $factory = User::factory();

        return ($role->isApp() ? $factory->app($role) : $factory->portal($role))->create();
    }

    protected function upload(User $owner, MediaPurpose $purpose): string
    {
        $image = imagecreatetruecolor(64, 48);

        ob_start();
        imagejpeg($image);
        $file = UploadedFile::fake()->createWithContent('scan.jpg', (string) ob_get_clean());

        $media = app(MediaLibrary::class)->upload($owner, $purpose, $file, ['width' => 64, 'height' => 48]);

        return app(MediaLibrary::class)->url($media);
    }

    protected function sendDocument(User $doctor, DocumentKind $kind): void
    {
        $url = $this->upload($doctor, MediaPurpose::DoctorDocument);

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/documents', ['kind' => $kind->value, 'file' => ['uri' => $url, 'width' => 64, 'height' => 48]])
            ->assertOk();
    }

    /**
     * A doctor with every field and file a submission needs.
     */
    protected function completeDoctor(): User
    {
        $doctor = $this->user(Role::Doctor);

        $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', self::PRACTICE)
            ->assertOk();

        foreach (DocumentKind::required(false) as $kind) {
            if ($kind !== DocumentKind::BankDetails) {
                $this->sendDocument($doctor, $kind);
            }
        }

        return $doctor;
    }

    protected function submittedDoctor(): User
    {
        $doctor = $this->completeDoctor();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertOk();

        return $doctor;
    }

    protected function approvedDoctor(array $practice = []): User
    {
        $doctor = $this->submittedDoctor();
        $admin = $this->user(Role::Admin);

        if ($practice !== []) {
            $this->actingAs($doctor)
                ->patchJson('api/v1/doctor/profile', $practice)
                ->assertOk();
        }

        foreach (DocumentKind::required(false) as $kind) {
            $this->actingAs($admin)
                ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review", ['kind' => $kind->value, 'decision' => 'accepted'])
                ->assertOk();
        }

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'approved'])
            ->assertOk();

        return $doctor;
    }
}
