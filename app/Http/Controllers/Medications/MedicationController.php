<?php

namespace App\Http\Controllers\Medications;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Medications\CreateMedicationRequest;
use App\Http\Requests\Medications\UpdateMedicationRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Medications\MedicationCollection;
use App\Http\Resources\Medications\MedicationResource;
use App\Services\Medications\Schedules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function __construct(
        private readonly Schedules $schedules,
    ) {}

    public function index(Request $request, string $userId): MedicationCollection
    {
        $patient = $this->schedules->readable($request->user(), $userId);
        $page = $this->schedules->of($patient, ListQuery::from($request));

        return new MedicationCollection($page);
    }

    public function store(CreateMedicationRequest $request): JsonResponse
    {
        $medication = $this->schedules->create($request->user(), $request->validated());

        return (new MedicationResource($medication))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function update(UpdateMedicationRequest $request, string $medId): MedicationResource
    {
        $medication = $this->schedules->update($request->user(), $medId, $request->validated());

        return new MedicationResource($medication);
    }

    public function pause(Request $request, string $medId): MedicationResource
    {
        $medication = $this->schedules->pause($request->user(), $medId);

        return new MedicationResource($medication);
    }

    public function resume(Request $request, string $medId): MedicationResource
    {
        $medication = $this->schedules->resume($request->user(), $medId);

        return new MedicationResource($medication);
    }

    public function destroy(Request $request, string $medId): EmptyResource
    {
        $this->schedules->delete($request->user(), $medId);

        return new EmptyResource;
    }
}
