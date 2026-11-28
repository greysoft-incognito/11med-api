<?php

namespace App\Services\Support;

use App\Enums\Role;
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
        $role = $requester->role();

        try {
            $found = match (true) {
                $kind === SupportLinkKind::Order && $role === Role::Patient => (bool) $this->patientOrders->find($requester, $id),
                $kind === SupportLinkKind::Order && $role === Role::Pharmacy => (bool) $this->pharmacyOrders->find($this->pharmacies->of($requester), $id),
                $kind === SupportLinkKind::LabBooking && $role === Role::Patient => (bool) $this->patientBookings->find($requester, $id),
                $kind === SupportLinkKind::LabBooking && $role === Role::Laboratory => (bool) $this->laboratoryBookings->find($this->laboratories->of($requester), $id),
                $kind === SupportLinkKind::Consultation && $role === Role::Patient => (bool) $this->consultations->ofPatient($requester, $id),
                $kind === SupportLinkKind::Consultation && $role === Role::Doctor => (bool) $this->consultations->ofDoctor($requester, $id),
                default => false,
            };
        } catch (ApiException) {
            $found = false;
        }

        if (! $found) {
            throw ApiException::unprocessable('SUPPORT_LINK_NOT_FOUND', 'link.id');
        }
    }
}
