<?php

namespace App\Http\Controllers\Doctors;

use App\Enums\DocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doctors\UpdateDoctorProfileRequest;
use App\Http\Requests\Doctors\UploadDocumentRequest;
use App\Http\Resources\Doctors\DoctorProfileResource;
use App\Services\Doctors\DoctorProfiles;
use Illuminate\Http\Request;

class DoctorProfileController extends Controller
{
    public function __construct(
        private readonly DoctorProfiles $profiles,
    ) {}

    public function show(Request $request): DoctorProfileResource
    {
        $profile = $this->profiles->of($request->user());

        return new DoctorProfileResource($profile);
    }

    public function update(UpdateDoctorProfileRequest $request): DoctorProfileResource
    {
        $profile = $this->profiles->update($request->user(), $request->validated());

        return new DoctorProfileResource($profile);
    }

    public function document(UploadDocumentRequest $request): DoctorProfileResource
    {
        $kind = DocumentKind::from($request->validated('kind'));
        $profile = $this->profiles->uploadDocument($request->user(), $kind, $request->validated('file.uri'));

        return new DoctorProfileResource($profile);
    }

    public function submit(Request $request): DoctorProfileResource
    {
        $profile = $this->profiles->submit($request->user());

        return new DoctorProfileResource($profile);
    }
}
