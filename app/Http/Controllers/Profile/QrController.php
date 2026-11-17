<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Profile\EmergencyRecordResource;
use App\Http\Resources\Profile\QrTokenResource;
use App\Services\Profile\EmergencyPage;
use App\Services\Profile\QrCodes;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function __construct(
        private readonly QrCodes $codes,
    ) {}

    public function show(Request $request, string $userId): QrTokenResource
    {
        $token = $this->codes->current($request->user(), $userId);

        return new QrTokenResource($token);
    }

    public function revoke(Request $request): QrTokenResource
    {
        $token = $this->codes->revoke($request->user());

        return new QrTokenResource($token);
    }

    /**
     * The public emergency page: no sign-in, by design.
     */
    public function view(Request $request, EmergencyPage $page): EmergencyRecordResource
    {
        $record = $page->open($request->string('token')->toString());

        return new EmergencyRecordResource($record);
    }
}
