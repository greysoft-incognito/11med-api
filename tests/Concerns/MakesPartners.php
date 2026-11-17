<?php

namespace Tests\Concerns;

use App\Enums\MediaPurpose;
use App\Enums\PartnerKind;
use App\Enums\PartnerStatus;
use App\Enums\Role;
use App\Models\Media;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\Partners\Organisations;
use Illuminate\Http\UploadedFile;

/**
 * Partners for feature tests: licences, approved organisations and their accounts, and pictures.
 */
trait MakesPartners
{
    protected function licenceUrl(): string
    {
        $library = app(MediaLibrary::class);
        $media = $library->upload(null, MediaPurpose::PartnerLicence, UploadedFile::fake()->createWithContent('licence.pdf', "%PDF-1.4\n%%EOF\n"), []);

        return $library->url($media);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function application(array $overrides = []): array
    {
        return [
            'kind' => 'pharmacy',
            'name' => 'Medplus Ikeja',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'address' => '12 Allen Avenue',
            'licence_number' => 'PCN-' . fake()->unique()->numerify('######'),
            'licence_url' => $this->licenceUrl(),
            'contact_name' => 'Ada Obi',
            'contact_phone' => '+2348031234567',
            'contact_email' => fake()->unique()->safeEmail(),
            ...$overrides,
        ];
    }

    /**
     * An approved partner and its contact's account.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: Organisation, 1: User}
     */
    protected function approvedPartner(PartnerKind $kind = PartnerKind::Pharmacy, array $overrides = []): array
    {
        $organisations = app(Organisations::class);
        $organisation = $organisations->apply($this->application(['kind' => $kind->value, ...$overrides]));
        $organisation = $organisations->review($organisation->uuid, 'approved', null);

        $account = User::query()
            ->where('organisation_id', $organisation->id)
            ->firstOrFail();

        return [$organisation->refresh(), $account];
    }

    protected function suspend(Organisation $organisation): void
    {
        $organisation->forceFill(['status' => PartnerStatus::Suspended])->save();
    }

    protected function admin(): User
    {
        return User::factory()
            ->portal(Role::Admin)
            ->create();
    }

    protected function picture(User $owner, MediaPurpose $purpose): string
    {
        $library = app(MediaLibrary::class);
        $image = imagecreatetruecolor(64, 64);

        ob_start();
        imagejpeg($image);
        $file = UploadedFile::fake()->createWithContent('picture.jpg', (string) ob_get_clean());

        $media = $library->upload($owner, $purpose, $file, ['width' => 64, 'height' => 64]);

        return $library->url($media);
    }

    protected function mediaFor(string $url): Media
    {
        return Media::query()
            ->where('uuid', str($url)->match('~[0-9a-f-]{36}~')->toString())
            ->firstOrFail();
    }
}
