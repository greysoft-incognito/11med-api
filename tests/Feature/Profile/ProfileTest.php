<?php

namespace Tests\Feature\Profile;

use App\Enums\AccountKind;
use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Models\MedicalProfile;
use App\Models\QrToken;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public const CORE = [
        'date_of_birth' => '1991-04-12',
        'gender' => 'female',
        'blood_type' => 'O+',
        'genotype' => 'AS',
        'state_of_residence' => 'Lagos',
        'lga' => 'Ikeja',
        'drug_allergies' => [
            ['drug_name' => 'Penicillin', 'reaction_type' => null, 'severity' => 'severe'],
        ],
        'emergency_contact' => [
            'name' => 'Chidi Okafor',
            'phone' => '+2348035550100',
            'relationship' => 'spouse',
            'address' => null,
        ],
    ];

    public function test_a_patient_creates_the_required_core(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/profile', self::CORE)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $patient->uuid)
            ->assertJsonPath('data.date_of_birth', '1991-04-12')
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.lga', 'Ikeja')
            ->assertJsonPath('data.drug_allergies.0.drug_name', 'Penicillin')
            ->assertJsonPath('data.drug_allergies.0.reaction_type', null)
            ->assertJsonPath('data.emergency_contact.phone', '08035550100')
            ->assertJsonPath('data.emergency_contact.is_verified', false)
            ->assertJsonPath('data.existing_conditions', [])
            ->assertJsonPath('data.hmo.provider', null)
            ->assertJsonPath('data.profile_photo_url', null)
            ->assertJsonStructure(['data' => ['updated_at']]);

        $this->assertTrue($patient->hasMedicalProfile());

        $this->actingAs($patient)
            ->postJson('api/v1/profile', self::CORE)
            ->assertConflict()
            ->assertJsonPath('error.code', 'PROFILE_EXISTS');
    }

    public function test_the_core_is_validated_field_by_field(): void
    {
        $patient = $this->patient();
        $cases = [
            'genotype' => ['genotype' => 'XX'],
            'state_of_residence' => ['state_of_residence' => 'Atlantis'],
            'emergency_contact.phone' => ['emergency_contact' => ['phone' => '12345'] + self::CORE['emergency_contact']],
            'drug_allergies.0.severity' => ['drug_allergies' => [['drug_name' => 'Sulfa', 'severity' => 'fatal']]],
            'date_of_birth' => ['date_of_birth' => now()->addDay()->toDateString()],
        ];

        foreach ($cases as $field => $change) {
            $this->actingAs($patient)
                ->postJson('api/v1/profile', [...self::CORE, ...$change])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.field', $field);
        }

        $this->actingAs($patient)
            ->postJson('api/v1/profile', [...self::CORE, 'lga' => 'Kano Municipal'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'lga');
    }

    public function test_only_the_owner_reads_and_anyone_else_is_told_it_does_not_exist(): void
    {
        $patient = $this->withProfile();
        $stranger = $this->patient();

        $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}")
            ->assertOk()
            ->assertJsonPath('data.genotype', 'AS');

        $this->actingAs($stranger)
            ->getJson("api/v1/profile/{$patient->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($stranger)
            ->getJson("api/v1/profile/{$stranger->uuid}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PROFILE_NOT_FOUND');

        $this->actingAs($stranger)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['genotype' => 'SS'])
            ->assertNotFound();
    }

    public function test_an_update_changes_only_what_it_sends(): void
    {
        $patient = $this->withProfile();

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", [
                'height_cm' => 168.5,
                'existing_conditions' => ['Hypertension'],
                'past_surgeries' => [['surgery_name' => 'Appendectomy']],
                'hmo' => ['provider' => 'Hygeia', 'expiry_date' => '2027-01-31'],
            ])
            ->assertOk()
            ->assertJsonPath('data.height_cm', 168.5)
            ->assertJsonPath('data.existing_conditions', ['Hypertension'])
            ->assertJsonPath('data.past_surgeries.0', ['surgery_name' => 'Appendectomy', 'date' => null, 'hospital' => null])
            ->assertJsonPath('data.hmo.provider', 'Hygeia')
            ->assertJsonPath('data.hmo.expiry_date', '2027-01-31')
            ->assertJsonPath('data.genotype', 'AS')
            ->assertJsonPath('data.drug_allergies.0.drug_name', 'Penicillin');

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['genotype' => null])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'genotype');
    }

    public function test_moving_state_drops_an_area_the_request_did_not_restate(): void
    {
        $patient = $this->withProfile();

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['state_of_residence' => 'Kano'])
            ->assertOk()
            ->assertJsonPath('data.lga', null);

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['lga' => 'Ikeja'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'lga');

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['state_of_residence' => 'Federal Capital Territory', 'lga' => 'Abuja Municipal'])
            ->assertOk()
            ->assertJsonPath('data.lga', 'Abuja Municipal');
    }

    public function test_a_new_contact_number_is_unconfirmed(): void
    {
        $patient = $this->withProfile();
        $patient->medicalProfile->forceFill(['contact_verified_at' => now()])->save();

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['emergency_contact' => ['address' => '3 Allen Avenue'] + self::CORE['emergency_contact']])
            ->assertOk()
            ->assertJsonPath('data.emergency_contact.is_verified', true)
            ->assertJsonPath('data.emergency_contact.address', '3 Allen Avenue');

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['emergency_contact' => ['phone' => '08099990000'] + self::CORE['emergency_contact']])
            ->assertOk()
            ->assertJsonPath('data.emergency_contact.is_verified', false);
    }

    public function test_the_photo_is_the_patients_own_upload_and_a_replaced_one_is_removed(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $patient = $this->withProfile();
        $first = $this->upload($patient);
        $second = $this->upload($patient);
        $other = $this->upload($this->patient());
        $library = app(MediaLibrary::class);

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['profile_photo_url' => $library->url($other)])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MEDIA_NOT_FOUND')
            ->assertJsonPath('error.field', 'profile_photo_url');

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['profile_photo_url' => $library->url($first)])
            ->assertOk()
            ->assertJsonPath('data.profile_photo_url', $library->url($first));

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['profile_photo_url' => $library->url($second)])
            ->assertOk();

        $this->assertModelMissing($first);
        Storage::disk('public')->assertMissing($first->path);

        $this->actingAs($patient)
            ->patchJson("api/v1/profile/{$patient->uuid}", ['profile_photo_url' => null])
            ->assertOk()
            ->assertJsonPath('data.profile_photo_url', null);

        $this->assertModelMissing($second);
    }

    public function test_erasure_takes_the_profile_and_its_codes(): void
    {
        $patient = $this->withProfile();

        $this->actingAs($patient)
            ->getJson("api/v1/profile/{$patient->uuid}/qr")
            ->assertOk();

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertSame(0, MedicalProfile::withTrashed()->count());
        $this->assertSame(0, QrToken::withTrashed()->count());
    }

    public function test_only_patients_have_a_medical_profile(): void
    {
        $doctor = User::factory()
            ->app(AccountKind::Doctor)
            ->create();

        $this->actingAs($doctor)
            ->postJson('api/v1/profile', self::CORE)
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->postJson('api/v1/profile', self::CORE)->assertUnauthorized();
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }

    private function withProfile(): User
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/profile', self::CORE)
            ->assertCreated();

        return $patient->refresh();
    }

    private function upload(User $owner): Media
    {
        $image = imagecreatetruecolor(64, 64);

        ob_start();
        imagejpeg($image);
        $file = UploadedFile::fake()->createWithContent('me.jpg', (string) ob_get_clean());

        return app(MediaLibrary::class)->upload($owner, MediaPurpose::ProfilePhoto, $file, ['width' => 64, 'height' => 64]);
    }
}
