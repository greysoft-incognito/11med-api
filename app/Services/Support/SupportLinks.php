<?php

namespace App\Services\Support;

use App\Enums\AccountKind;
use App\Enums\SupportLinkKind;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Consultations\ConsultationRecords;
use App\Services\Laboratories\LabBookings;
use App\Services\Laboratories\Laboratories;
use App\Services\Laboratories\LaboratoryBookings;
use App\Services\Pharmacy\OrderBook;
use App\Services\Pharmacy\PatientOrders;
use App\Services\Pharmacy\PharmacyStore;

/**
 * Checks that the order, booking or consultation a ticket names is one the requester may see, by
 * asking the module that owns it. Anything else is 422 `SUPPORT_LINK_NOT_FOUND` on `link.id`.
 */
class SupportLinks
{
    public function __construct(
        private readonly PatientOrders $patientOrders,
        private readonly PharmacyStore $pharmacies,
        private readonly OrderBook $pharmacyOrders,
        private readonly LabBookings $patientBookings,
        private readonly Laboratories $laboratories,
        private readonly LaboratoryBookings $laboratoryBookings,
        private readonly ConsultationRecords $consultations,
    ) {}

    public function ensureVisible(User $requester, SupportLinkKind $kind, string $id): void
    {
        $checks = [
            [SupportLinkKind::Order, AccountKind::Patient, fn() => $this->patientOrders->find($requester, $id)],
            [SupportLinkKind::Order, AccountKind::Pharmacy, fn() => $this->pharmacyOrders->find($this->pharmacies->of($requester), $id)],
            [SupportLinkKind::LabBooking, AccountKind::Patient, fn() => $this->patientBookings->find($requester, $id)],
            [SupportLinkKind::LabBooking, AccountKind::Laboratory, fn() => $this->laboratoryBookings->find($this->laboratories->of($requester), $id)],
            [SupportLinkKind::Consultation, AccountKind::Patient, fn() => $this->consultations->ofPatient($requester, $id)],
            [SupportLinkKind::Consultation, AccountKind::Doctor, fn() => $this->consultations->ofDoctor($requester, $id)],
        ];

        $found = false;

        foreach ($checks as [$linkKind, $accountKind, $find]) {
            if ($found || $linkKind !== $kind || ! $requester->holds($accountKind)) {
                continue;
            }

            try {
                $found = (bool) $find();
            } catch (ApiException) {
                $found = false;
            }
        }

        if (! $found) {
            throw ApiException::unprocessable('SUPPORT_LINK_NOT_FOUND', 'link.id');
        }
    }
}
