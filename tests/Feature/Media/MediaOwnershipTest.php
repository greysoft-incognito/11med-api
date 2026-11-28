<?php

namespace Tests\Feature\Media;

use App\Enums\AccountKind;
use App\Enums\MediaPurpose;
use App\Exceptions\ApiException;
use App\Models\Media;
use App\Models\User;
use App\Services\Identity\AccountEraser;
use App\Services\Media\MediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaOwnershipTest extends TestCase
{
    private MediaLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->library = app(MediaLibrary::class);
    }

    public function test_the_uploader_claims_their_own_file_for_its_purpose(): void
    {
        $patient = $this->patient();
        $media = $this->photo($patient);
        $url = $this->library->url($media);

        $claimed = $this->library->claim($patient, $url, MediaPurpose::ProfilePhoto, 'profile_photo_url');

        $this->assertTrue($claimed->is($media));
        $this->assertNotNull($claimed->claimed_at);

        $again = $this->library->claim($patient, $url, MediaPurpose::ProfilePhoto, 'profile_photo_url');

        $this->assertTrue($again->is($media));
    }

    public function test_nobody_claims_someone_elses_file_or_one_made_for_another_purpose(): void
    {
        $patient = $this->patient();
        $stranger = $this->patient();
        $url = $this->library->url($this->photo($patient));

        $this->assertRefused(fn() => $this->library->claim($stranger, $url, MediaPurpose::ProfilePhoto, 'profile_photo_url'), 'profile_photo_url');
        $this->assertRefused(fn() => $this->library->claim($patient, $url, MediaPurpose::MessageAttachment, 'attachment_url'), 'attachment_url');
        $this->assertRefused(fn() => $this->library->claim($patient, 'https://example.com/me.jpg', MediaPurpose::ProfilePhoto, 'profile_photo_url'), 'profile_photo_url');
    }

    public function test_a_signed_link_names_its_file_whatever_its_signature(): void
    {
        $patient = $this->patient();
        $media = $this->library->upload($patient, MediaPurpose::MessageAttachment, $this->pdfFile(), []);
        $url = $this->library->url($media);

        $this->assertStringContainsString('signature=', $url);

        $this->travel(1)->hours();

        $claimed = $this->library->claim($patient, $url, MediaPurpose::MessageAttachment, 'attachment_url');

        $this->assertTrue($claimed->is($media));
    }

    public function test_a_licence_is_claimed_by_one_application_only(): void
    {
        $media = $this->library->upload(null, MediaPurpose::PartnerLicence, $this->pdfFile(), []);
        $url = $this->library->url($media);

        $this->library->claim(null, $url, MediaPurpose::PartnerLicence, 'licence_url');

        $this->assertRefused(fn() => $this->library->claim(null, $url, MediaPurpose::PartnerLicence, 'licence_url'), 'licence_url');
    }

    public function test_uploads_nothing_claimed_within_a_day_are_pruned_with_their_files(): void
    {
        $patient = $this->patient();
        $orphan = $this->photo($patient);
        $kept = $this->photo($patient);

        $this->library->claim($patient, $this->library->url($kept), MediaPurpose::ProfilePhoto, 'profile_photo_url');

        $this->travel(25)->hours();

        $fresh = $this->photo($patient);

        Artisan::call('model:prune', ['--model' => [Media::class]]);

        $this->assertModelMissing($orphan);
        $this->assertModelExists($kept);
        $this->assertModelExists($fresh);
        Storage::disk('public')->assertMissing($orphan->path);
        Storage::disk('public')->assertExists($kept->path);
    }

    public function test_an_erased_account_takes_its_uploads_with_it(): void
    {
        $patient = $this->patient();
        $photo = $this->photo($patient);
        $attachment = $this->library->upload($patient, MediaPurpose::MessageAttachment, $this->pdfFile(), []);
        $other = $this->photo($this->patient());

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertModelMissing($photo);
        $this->assertModelMissing($attachment);
        $this->assertModelExists($other);
        Storage::disk('public')->assertMissing($photo->path);
        Storage::disk('local')->assertMissing($attachment->path);
        Storage::disk('public')->assertExists($other->path);
    }

    public function test_a_discarded_file_is_gone_for_real(): void
    {
        $media = $this->photo($this->patient());

        $this->library->discard($media);

        $this->assertSame(0, Media::withTrashed()->count());
        Storage::disk('public')->assertMissing($media->path);
    }

    private function assertRefused(callable $claim, string $field): void
    {
        try {
            $claim();
            $this->fail('The claim was accepted.');
        } catch (ApiException $e) {
            $this->assertSame('MEDIA_NOT_FOUND', $e->errorCode);
            $this->assertSame(422, $e->status->value);
            $this->assertSame($field, $e->field);
        }
    }

    private function patient(): User
    {
        return User::factory()
            ->app(AccountKind::Patient)
            ->create();
    }

    private function photo(User $owner): Media
    {
        $image = imagecreatetruecolor(64, 64);

        ob_start();
        imagejpeg($image);
        $file = UploadedFile::fake()->createWithContent('me.jpg', (string) ob_get_clean());

        return $this->library->upload($owner, MediaPurpose::ProfilePhoto, $file, ['width' => 64, 'height' => 64]);
    }

    private function pdfFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('result.pdf', "%PDF-1.4\n%%EOF\n");
    }
}
