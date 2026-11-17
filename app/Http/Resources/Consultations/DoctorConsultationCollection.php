<?php

namespace App\Http\Resources\Consultations;

/**
 * The doctor's inbox: waiting first, then the newest, paged (FE-003).
 */
class DoctorConsultationCollection extends ConsultationCollection
{
    public $collects = DoctorConsultationResource::class;
}
