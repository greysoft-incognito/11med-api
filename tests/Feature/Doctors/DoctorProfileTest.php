<?php

namespace Tests\Feature\Doctors;

use App\Enums\AccountKind;
use App\Enums\DocumentKind;
use App\Enums\MediaPurpose;
use App\Models\DoctorDocument;
use App\Models\DoctorProfile;
use App\Models\Media;
use App\Services\Identity\AccountEraser;
use Tests\TestCase;

class DoctorProfileTest extends TestCase
{
    use MakesDoctors;

    public function test_a_doctor_reads_a_blank_record_on_first_open(): void
    {
        $doctor = $this->user(AccountKind::Doctor);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/profile')
            ->assertOk()
            ->assertJsonPath('data.user_id', $doctor->uuid)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.submitted_at', null)
            ->assertJsonPath('data.languages', [])
            ->assertJsonPath('data.session_minutes', 30)
            ->assertJsonPath('data.chat_window_hours', 24)
            ->assertJsonPath('data.bank_account_masked', null)
            ->assertJsonCount(6, 'data.documents')
            ->assertJsonPath('data.documents.0', [
                'kind' => 'mdcn_certificate',
                'status' => 'missing',
                'uploaded_at' => null,
                'rejection_reason' => null,
            ]);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/profile')
            ->assertOk();

        $this->assertSame(1, DoctorProfile::query()->count());
    }

    public function test_other_roles_are_refused(): void
    {
        foreach ([$this->user(AccountKind::Patient), $this->admin(), $this->user(AccountKind::Pharmacy)] as $account) {
            $this->actingAs($account)
                ->getJson('api/v1/doctor/profile')
                ->assertForbidden();
        }

        $this->app['auth']->forgetGuards();

        $this->getJson('api/v1/doctor/profile')->assertUnauthorized();
    }

    public function test_the_practice_details_save_and_the_account_is_masked(): void
    {
        $doctor = $this->user(AccountKind::Doctor);
        $photo = $this->upload($doctor, MediaPurpose::DoctorPhoto);

        $response = $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', [
                ...self::PRACTICE,
                'photo_url' => $photo,
                'session_minutes' => 45,
                'chat_window_hours' => 48,
                'follow_up_fee_kobo' => 200000,
                'availability' => [['day' => 1, 'start' => '09:00', 'end' => '13:00']],
                'is_available_now' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.specialty', 'cardiology')
            ->assertJsonPath('data.photo_url', $photo)
            ->assertJsonPath('data.bank_account_masked', '••••••6789')
            ->assertJsonPath('data.session_minutes', 45)
            ->assertJsonPath('data.availability.0.start', '09:00')
            ->assertJsonPath('data.documents.5.kind', 'bank_details')
            ->assertJsonPath('data.documents.5.status', 'uploaded')
            ->assertJsonMissingPath('data.bank_account_number');

        $this->assertStringNotContainsString('0123456789', $response->getContent());
        $this->assertNotSame('0123456789', DoctorProfile::query()->toBase()->value('bank_account_number'));
        $this->assertSame('0123456789', DoctorProfile::query()->first()->bank_account_number);
    }

    public function test_the_practice_details_are_validated(): void
    {
        $doctor = $this->user(AccountKind::Doctor);
        $cases = [
            'specialty' => ['specialty' => 'surgery'],
            'languages.0' => ['languages' => ['french']],
            'bio' => ['bio' => str_repeat('a', 501)],
            'session_minutes' => ['session_minutes' => 25],
            'chat_window_hours' => ['chat_window_hours' => 6],
            'bank_account_number' => ['bank_account_number' => '12345'],
            'availability.0.day' => ['availability' => [['day' => 7, 'start' => '09:00', 'end' => '10:00']]],
            'availability.0.end' => ['availability' => [['day' => 1, 'start' => '13:00', 'end' => '09:00']]],
            'fee_chat_kobo' => ['fee_chat_kobo' => -1],
        ];

        foreach ($cases as $field => $body) {
            $this->actingAs($doctor)
                ->patchJson('api/v1/doctor/profile', $body)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', $field);
        }
    }

    public function test_a_photo_must_be_the_doctors_own_upload_for_it(): void
    {
        $doctor = $this->user(AccountKind::Doctor);
        $other = $this->user(AccountKind::Doctor);
        $document = $this->upload($doctor, MediaPurpose::DoctorDocument);

        foreach ([$this->upload($other, MediaPurpose::DoctorPhoto), $document] as $url) {
            $this->actingAs($doctor)
                ->patchJson('api/v1/doctor/profile', ['photo_url' => $url])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
                ->assertJsonPath('error.field', 'photo_url');
        }
    }

    public function test_a_document_is_uploaded_and_replaced(): void
    {
        $doctor = $this->user(AccountKind::Doctor);

        $this->sendDocument($doctor, DocumentKind::GovernmentId);
        $this->sendDocument($doctor, DocumentKind::GovernmentId);

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/profile')
            ->assertJsonPath('data.documents.1.kind', 'government_id')
            ->assertJsonPath('data.documents.1.status', 'uploaded');

        $this->assertSame(1, $doctor->doctorProfile->documents()->count());
        $this->assertSame(1, $doctor->doctorProfile->documents()->first()->media()->count());
    }

    public function test_a_document_must_be_the_doctors_own_upload(): void
    {
        $doctor = $this->user(AccountKind::Doctor);
        $stranger = $this->upload($this->user(AccountKind::Doctor), MediaPurpose::DoctorDocument);
        $photo = $this->upload($doctor, MediaPurpose::DoctorPhoto);

        foreach ([$stranger, $photo, 'file:///var/mobile/scan.jpg'] as $uri) {
            $this->actingAs($doctor)
                ->postJson('api/v1/doctor/documents', ['kind' => 'mdcn_certificate', 'file' => ['uri' => $uri, 'width' => 64, 'height' => 48]])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
                ->assertJsonPath('error.field', 'file.uri');
        }

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/documents', ['kind' => 'cv', 'file' => ['uri' => $photo, 'width' => 64, 'height' => 48]])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'kind');

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/documents', ['kind' => 'mdcn_certificate'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'file');
    }

    public function test_an_incomplete_submission_names_what_is_missing(): void
    {
        $doctor = $this->user(AccountKind::Doctor);

        $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', ['mdcn_number' => 'MD1', 'sub_specialty' => 'Electrophysiology'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'mdcn_number');

        $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', ['mdcn_number' => 'MDCN/12345', 'sub_specialty' => 'Electrophysiology'])
            ->assertOk();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PROFILE_INCOMPLETE')
            ->assertJsonPath('error.meta.missing', [
                'specialty', 'years_of_experience', 'languages', 'bio', 'fees', 'bank_details',
                'mdcn_certificate', 'government_id', 'degree_certificate', 'specialist_certificate', 'passport_photograph',
            ]);
    }

    public function test_a_complete_profile_submits_once(): void
    {
        $doctor = $this->completeDoctor();

        $first = $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->json('data.submitted_at');

        $this->assertNotNull($first);

        $this->travel(5)->minutes();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertOk()
            ->assertJsonPath('data.submitted_at', $first);
    }

    public function test_the_mdcn_number_is_fixed_once_submitted(): void
    {
        $doctor = $this->submittedDoctor();

        $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', ['mdcn_number' => 'MDCN/99999'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MDCN_NUMBER_LOCKED')
            ->assertJsonPath('error.field', 'mdcn_number');

        $this->actingAs($doctor)
            ->patchJson('api/v1/doctor/profile', ['mdcn_number' => 'MDCN/12345', 'bio' => 'Updated.'])
            ->assertOk();
    }

    public function test_an_approved_or_suspended_doctor_does_not_resubmit(): void
    {
        $doctor = $this->approvedDoctor();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_APPROVED');

        $this->actingAs($this->admin())
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'suspended', 'notes' => 'Complaint under investigation.'])
            ->assertOk();

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DOCTOR_SUSPENDED');
    }

    public function test_a_rejected_application_resubmits_after_fixing_the_document(): void
    {
        $doctor = $this->submittedDoctor();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/documents/review", ['kind' => 'mdcn_certificate', 'decision' => 'rejected', 'reason' => 'The scan is cut off at the registration number.'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("api/v1/portal/admin/doctors/{$doctor->uuid}/review", ['decision' => 'rejected', 'notes' => 'Upload a full scan of the MDCN certificate.'])
            ->assertOk();

        $this->actingAs($doctor)
            ->getJson('api/v1/doctor/profile')
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.verification_notes', 'Upload a full scan of the MDCN certificate.')
            ->assertJsonPath('data.documents.0.rejection_reason', 'The scan is cut off at the registration number.');

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertUnprocessable()
            ->assertJsonPath('error.meta.missing', ['mdcn_certificate']);

        $this->sendDocument($doctor, DocumentKind::MdcnCertificate);

        $this->actingAs($doctor)
            ->postJson('api/v1/doctor/profile/submit')
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.verification_notes', null);
    }

    public function test_erasing_a_doctor_removes_the_record_and_the_files(): void
    {
        $doctor = $this->submittedDoctor();

        app(AccountEraser::class)->erase($doctor, 'Password1', null);

        $this->assertSame(0, DoctorProfile::withTrashed()->count());
        $this->assertSame(0, DoctorDocument::withTrashed()->count());
        $this->assertSame(0, Media::withTrashed()->count());
    }
}
