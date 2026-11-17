<?php

namespace Tests\Feature\Media;

use App\Enums\Role;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UploadMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_a_patient_uploads_a_profile_photo_and_its_metadata_is_gone(): void
    {
        $patient = $this->account(Role::Patient);
        $file = UploadedFile::fake()->createWithContent('me.jpg', $this->jpeg(800, 600, withExif: true));

        $response = $this->upload($patient, ['purpose' => 'profile_photo', 'file' => $file, 'width' => 800, 'height' => 600])
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'profile_photo')
            ->assertJsonPath('data.name', 'me.jpg')
            ->assertJsonPath('data.width', 800)
            ->assertJsonPath('data.height', 600)
            ->assertJsonPath('data.content_type', 'image/jpeg')
            ->assertJsonStructure(['data' => ['id', 'url', 'uploaded_at']]);

        $media = Media::sole();
        $stored = Storage::disk('public')->get($media->path);

        $this->assertSame($patient->id, $media->user_id);
        $this->assertSame($media->uuid, $response->json('data.id'));
        $this->assertStringContainsString($media->uuid, $media->path);
        $this->assertSame(Storage::disk('public')->url($media->path), $response->json('data.url'));
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('HOME-GPS', $stored);
        $this->assertSame([800, 600], array_slice(getimagesizefromstring($stored), 0, 2));
    }

    public function test_an_attachment_is_served_only_through_a_signed_link(): void
    {
        $patient = $this->account(Role::Patient);
        $file = UploadedFile::fake()->createWithContent('result.pdf', $this->pdf());

        $url = $this->upload($patient, ['purpose' => 'message_attachment', 'file' => $file, 'size' => 120])
            ->assertCreated()
            ->assertJsonPath('data.width', null)
            ->assertJsonPath('data.height', null)
            ->assertJsonPath('data.content_type', 'application/pdf')
            ->json('data.url');

        $this->assertSame(0, count(Storage::disk('public')->allFiles()));

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->getJson(strtok($url, '?'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'LINK_EXPIRED');

        $this->travel(16)->minutes();

        $this->getJson($url)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'LINK_EXPIRED');
    }

    public function test_each_portal_uploads_for_its_own_purposes(): void
    {
        $pharmacy = $this->account(Role::Pharmacy);
        $laboratory = $this->account(Role::Laboratory);
        $picture = fn() => UploadedFile::fake()->createWithContent('pack.png', $this->png(400, 300));

        $this->upload($pharmacy, ['purpose' => 'drug_image', 'file' => $picture(), 'width' => 400, 'height' => 300])
            ->assertCreated()
            ->assertJsonPath('data.content_type', 'image/png');

        $this->upload($laboratory, ['purpose' => 'drug_image', 'file' => $picture(), 'width' => 400, 'height' => 300])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->upload($laboratory, ['purpose' => 'lab_results', 'file' => $picture(), 'width' => 400, 'height' => 300])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE')
            ->assertJsonPath('error.field', 'file');
    }

    public function test_a_licence_needs_no_account_and_is_limited_per_address(): void
    {
        $file = fn() => UploadedFile::fake()->createWithContent('licence.pdf', $this->pdf());

        $this->postJson('api/v1/media', ['purpose' => 'partner_licence', 'file' => $file()])
            ->assertCreated();

        $this->assertNull(Media::sole()->user_id);

        foreach (range(2, config('elevenmed.media.public_uploads_per_hour')) as $ignored) {
            $this->postJson('api/v1/media', ['purpose' => 'partner_licence', 'file' => $file()])->assertCreated();
        }

        $this->postJson('api/v1/media', ['purpose' => 'partner_licence', 'file' => $file()])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonStructure(['error' => ['meta' => ['retry_after_ms']]]);
    }

    public function test_every_other_purpose_needs_an_account(): void
    {
        $file = UploadedFile::fake()->createWithContent('me.jpg', $this->jpeg(100, 100));

        $this->postJson('api/v1/media', ['purpose' => 'profile_photo', 'file' => $file, 'width' => 100, 'height' => 100])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_a_bearer_token_is_read_without_the_auth_middleware(): void
    {
        $patient = $this->account(Role::Patient);
        $live = $patient->createToken('app', ['*'], now()->addMinutes(15))->plainTextToken;
        $expired = $patient->createToken('app', ['*'], now()->subMinute())->plainTextToken;
        $file = fn() => UploadedFile::fake()->createWithContent('me.jpg', $this->jpeg(100, 100));

        $this->withToken($expired)
            ->postJson('api/v1/media', ['purpose' => 'profile_photo', 'file' => $file(), 'width' => 100, 'height' => 100])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');

        $this->app['auth']->forgetGuards();

        $this->withToken($live)
            ->postJson('api/v1/media', ['purpose' => 'profile_photo', 'file' => $file(), 'width' => 100, 'height' => 100])
            ->assertCreated();
    }

    public function test_the_request_itself_is_checked(): void
    {
        $patient = $this->account(Role::Patient);

        $this->upload($patient, ['purpose' => 'selfie'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'purpose');

        $this->upload($patient, ['purpose' => 'profile_photo'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'NO_FILE')
            ->assertJsonPath('error.field', 'file');
    }

    public function test_a_file_is_judged_by_its_bytes_not_its_name(): void
    {
        $patient = $this->account(Role::Patient);
        $disguised = UploadedFile::fake()->createWithContent('me.jpg', 'not a picture at all');
        $pdf = UploadedFile::fake()->createWithContent('me.jpg', $this->pdf());

        $this->upload($patient, ['purpose' => 'profile_photo', 'file' => $disguised, 'width' => 10, 'height' => 10])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');

        $this->upload($patient, ['purpose' => 'profile_photo', 'file' => $pdf])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_a_pdf_that_can_run_something_is_refused(): void
    {
        $doctor = $this->account(Role::Doctor);
        $file = UploadedFile::fake()->createWithContent('letter.pdf', $this->pdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>'));

        $this->upload($doctor, ['purpose' => 'message_attachment', 'file' => $file])
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_too_large_by_bytes_or_by_declaration(): void
    {
        $doctor = $this->account(Role::Doctor);
        $huge = UploadedFile::fake()->create('scan.pdf', 5 * 1024 + 1, 'application/pdf');
        $declared = UploadedFile::fake()->createWithContent('scan.pdf', $this->pdf());

        $this->upload($doctor, ['purpose' => 'message_attachment', 'file' => $huge])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'FILE_TOO_LARGE')
            ->assertJsonPath('error.field', 'file');

        $this->upload($doctor, ['purpose' => 'message_attachment', 'file' => $declared, 'size' => 6 * 1024 * 1024])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'FILE_TOO_LARGE');
    }

    public function test_a_picture_must_be_resized_and_say_so_truthfully(): void
    {
        $doctor = $this->account(Role::Doctor);
        $big = fn() => UploadedFile::fake()->createWithContent('me.jpg', $this->jpeg(1700, 900));
        $small = fn() => UploadedFile::fake()->createWithContent('me.jpg', $this->jpeg(800, 600));

        $cases = [
            ['file' => $big(), 'width' => 1700, 'height' => 900],
            ['file' => $small()],
            ['file' => $small(), 'width' => 400, 'height' => 300],
        ];

        foreach ($cases as $case) {
            $this->upload($doctor, ['purpose' => 'doctor_photo', ...$case])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'IMAGE_NOT_RESIZED')
                ->assertJsonPath('error.field', 'file');
        }

        $this->assertSame(0, Media::count());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function upload(User $user, array $body): TestResponse
    {
        return $this->actingAs($user, 'sanctum')->postJson('api/v1/media', $body);
    }

    private function account(Role $role): User
    {
        $factory = User::factory();

        return $role->isApp()
            ? $factory->app($role)->create()
            : $factory->portal($role)->create();
    }

    private function jpeg(int $width, int $height, bool $withExif = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();

        if (! $withExif) {
            return $bytes;
        }

        $payload = "Exif\0\0MM\0\x2a\0\0\0\x08\0\0HOME-GPS 6.5244,3.3792";
        $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($bytes, 0, 2) . $segment . substr($bytes, 2);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function pdf(string $catalogue = ''): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog {$catalogue} >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }
}
