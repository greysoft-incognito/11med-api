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
    case DoctorDocument = 'doctor_document';
    case MessageAttachment = 'message_attachment';
    case DrugImage = 'drug_image';
    case PharmacyLogo = 'pharmacy_logo';
    case PharmacyCover = 'pharmacy_cover';
    case LaboratoryLogo = 'laboratory_logo';
    case LaboratoryCover = 'laboratory_cover';
    case LabResults = 'lab_results';
    case PartnerLicence = 'partner_licence';
    case SupportAttachment = 'support_attachment';

    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const DOCUMENT_TYPES = ['application/pdf'];

    /**
     * The account kinds that may upload for this purpose; empty when no account is needed.
     *
     * @return list<AccountKind>
     */
    public function kinds(): array
    {
        return match ($this) {
            self::ProfilePhoto => [AccountKind::Patient],
            self::DoctorPhoto, self::DoctorDocument => [AccountKind::Doctor],
            self::MessageAttachment => [AccountKind::Patient, AccountKind::Doctor],
            self::DrugImage, self::PharmacyLogo, self::PharmacyCover => [AccountKind::Pharmacy],
            self::LaboratoryLogo, self::LaboratoryCover, self::LabResults => [AccountKind::Laboratory],
            self::PartnerLicence => [],
            self::SupportAttachment => [AccountKind::Patient, AccountKind::Doctor, AccountKind::Pharmacy, AccountKind::Laboratory],
        };
    }

    /**
     * The licence arrives with an application, before there is an account to own it.
     */
    public function isPublicUpload(): bool
    {
        return $this->kinds() === [];
    }

    /**
     * @return list<string>
     */
    public function contentTypes(): array
    {
        return match ($this) {
            self::LabResults => self::DOCUMENT_TYPES,
            self::MessageAttachment, self::PartnerLicence, self::SupportAttachment => [...self::IMAGE_TYPES, ...self::DOCUMENT_TYPES],
            default => self::IMAGE_TYPES,
        };
    }

    /**
     * Medical files, a doctor's verification documents, licences and support attachments are served by signed, short-lived URLs; store pictures are public.
     */
    public function isPrivate(): bool
    {
        return in_array($this, [self::DoctorDocument, self::MessageAttachment, self::LabResults, self::PartnerLicence, self::SupportAttachment], true);
    }

    public static function isImage(string $contentType): bool
    {
        return in_array($contentType, self::IMAGE_TYPES, true);
    }
}
