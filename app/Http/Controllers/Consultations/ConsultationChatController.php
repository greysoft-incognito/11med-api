<?php

namespace App\Http\Controllers\Consultations;

use App\Enums\HttpStatus;
use App\Enums\MessageSender;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Consultations\SendMessageRequest;
use App\Http\Resources\Consultations\ConsultationMessageCollection;
use App\Http\Resources\Consultations\ConsultationMessageResource;
use App\Services\Consultations\Chat;
use App\Services\Consultations\ConsultationRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chat from either side (app spec §7.4); each side reaches only its own consultations.
 */
class ConsultationChatController extends Controller
{
    public function __construct(
        private readonly ConsultationRecords $records,
        private readonly Chat $chat,
    ) {}

    public function patientMessages(Request $request, string $id): ConsultationMessageCollection
    {
        $page = $this->chat->messages($this->records->ofPatient($request->user(), $id), ListQuery::from($request, ListQuery::MAX_LIMIT));

        return new ConsultationMessageCollection($page);
    }

    public function patientSend(SendMessageRequest $request, string $id): JsonResponse
    {
        $message = $this->chat->send($this->records->ofPatient($request->user(), $id), $request->user(), MessageSender::Patient, $request->validated(), 'CONSULTATION_NOT_ACTIVE');

        return (new ConsultationMessageResource($message))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function doctorMessages(Request $request, string $id): ConsultationMessageCollection
    {
        $page = $this->chat->messages($this->records->ofDoctor($request->user(), $id), ListQuery::from($request, ListQuery::MAX_LIMIT));

        return new ConsultationMessageCollection($page);
    }

    public function doctorSend(SendMessageRequest $request, string $id): ConsultationMessageResource
    {
        $message = $this->chat->send($this->records->ofDoctor($request->user(), $id), $request->user(), MessageSender::Doctor, $request->validated(), 'NOT_ACTIVE');

        return new ConsultationMessageResource($message);
    }
}
