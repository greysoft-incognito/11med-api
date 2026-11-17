<?php

namespace App\Http\Controllers\Doctors;

use App\Enums\ConsultLanguage;
use App\Enums\Specialty;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Resources\Doctors\DoctorCollection;
use App\Http\Resources\Doctors\DoctorResource;
use App\Http\Resources\Doctors\DoctorSlotsResource;
use App\Services\Doctors\DoctorDirectory;
use App\Services\Doctors\DoctorSlots;
use Illuminate\Http\Request;

class DoctorDirectoryController extends Controller
{
    public function __construct(
        private readonly DoctorDirectory $directory,
        private readonly DoctorSlots $slots,
    ) {}

    public function index(Request $request): DoctorCollection
    {
        $specialty = Specialty::tryFrom($request->string('specialty')->toString());
        $language = ConsultLanguage::tryFrom($request->string('language')->toString());
        $availableNow = $request->string('available_now')->toString() === 'true';
        $list = ListQuery::from($request);

        $page = $this->directory->list($request->user(), $list, $specialty, $language, $availableNow, $request->string('q')->toString());

        return new DoctorCollection($page);
    }

    public function show(Request $request, string $doctorId): DoctorResource
    {
        $profile = $this->directory->find($request->user(), $doctorId);

        return new DoctorResource($profile);
    }

    public function slots(string $doctorId): DoctorSlotsResource
    {
        $profile = $this->directory->bookable($doctorId);
        $slots = [
            'has_schedule' => $profile->availability !== [],
            'slot_minutes' => $profile->session_minutes,
            'slots' => $this->slots->open($profile, now()),
        ];

        return new DoctorSlotsResource($slots);
    }
}
