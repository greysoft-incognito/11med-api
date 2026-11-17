<?php

namespace App\Enums;

/**
 * What an upload is for (app and portal `src/api/contracts/media.ts`). The purpose decides
 * who may upload it, what types it takes, and whether its URL is public or signed.
 */
enum MediaPurpose: string
{
    case ProfilePhoto = 'profile_photo';
    case DoctorPhoto = 'doctor_photo';
    case MessageAttachment = 'message_attachment';
    case DrugImage = 'drug_image';
    case PharmacyLogo = 'pharmacy_logo';
    case PharmacyCover = 'pharmacy_cover';
    case LaboratoryLogo = 'laboratory_logo';
    case LaboratoryCover = 'laboratory_cover';
    case LabResults = 'lab_results';
    case PartnerLicence = 'partner_licence';

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const DOCUMENT_TYPES = ['application/pdf'];

    /**
     * The roles that may upload for this purpose; empty when no account is needed.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::ProfilePhoto => [Role::Patient],
            self::DoctorPhoto => [Role::Doctor],
            self::MessageAttachment => [Role::Patient, Role::Doctor],
            self::DrugImage, self::PharmacyLogo, self::PharmacyCover => [Role::Pharmacy],
            self::LaboratoryLogo, self::LaboratoryCover, self::LabResults => [Role::Laboratory],
            self::PartnerLicence => [],
        };
    }

    /**
     * The licence arrives with an application, before there is an account to own it.
     */
    public function isPublicUpload(): bool
    {
        return $this->roles() === [];
    }

    /**
     * @return list<string>
     */
    public function contentTypes(): array
    {
        return match ($this) {
            self::LabResults => self::DOCUMENT_TYPES,
            self::MessageAttachment, self::PartnerLicence => [...self::IMAGE_TYPES, ...self::DOCUMENT_TYPES],
            default => self::IMAGE_TYPES,
        };
    }

    /**
     * Medical files and licences are served by signed, short-lived URLs; store pictures are public.
     */
    public function isPrivate(): bool
    {
        return in_array($this, [self::MessageAttachment, self::LabResults, self::PartnerLicence], true);
    }

    public static function isImage(string $contentType): bool
    {
        return in_array($contentType, self::IMAGE_TYPES, true);
    }
}
