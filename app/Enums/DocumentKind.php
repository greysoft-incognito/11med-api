<?php

namespace App\Enums;

/**
 * The six things a doctor's review needs (app `zDocumentKind`, concept §4.2), in the review's order.
 */
enum DocumentKind: string
{
    case MdcnCertificate = 'mdcn_certificate';
    case GovernmentId = 'government_id';
    case DegreeCertificate = 'degree_certificate';
    case SpecialistCertificate = 'specialist_certificate';
    case PassportPhotograph = 'passport_photograph';
    case BankDetails = 'bank_details';

    /**
     * The documents an approval needs: the specialist certificate only with a sub-specialty.
     *
     * @return list<self>
     */
    public static function required(bool $hasSubSpecialty): array
    {
        return array_values(array_filter(
            self::cases(),
            fn(self $kind) => $kind !== self::SpecialistCertificate || $hasSubSpecialty
        ));
    }
}
