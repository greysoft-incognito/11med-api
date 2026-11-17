<?php

namespace App\Http\Controllers\Profile;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CreateProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\MedicalProfileResource;
use App\Services\Profile\MedicalProfiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly MedicalProfiles $profiles,
    ) {}

    public function store(CreateProfileRequest $request): JsonResponse
    {
        $profile = $this->profiles->create($request->user(), $request->validated());

        return (new MedicalProfileResource($profile))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Request $request, string $userId): MedicalProfileResource
    {
        $profile = $this->profiles->forOwner($request->user(), $userId);

        return new MedicalProfileResource($profile);
    }

    public function update(UpdateProfileRequest $request, string $userId): MedicalProfileResource
    {
        $profile = $this->profiles->update($request->user(), $userId, $request->validated());

        return new MedicalProfileResource($profile);
    }
}
