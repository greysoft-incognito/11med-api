<?php

namespace App\Http\Controllers\Support;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Support\OpenTicketRequest;
use App\Http\Requests\Support\SupportReplyRequest;
use App\Http\Resources\Support\SupportMessageCollection;
use App\Http\Resources\Support\SupportMessageResource;
use App\Http\Resources\Support\SupportTicketCollection;
use App\Http\Resources\Support\SupportTicketResource;
use App\Services\Support\SupportTickets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contact support, for app and partner portal accounts.
 */
class SupportController extends Controller
{
    public function __construct(
        private readonly SupportTickets $tickets,
    ) {}

    public function index(Request $request): SupportTicketCollection
    {
        $page = $this->tickets->list($request->user(), ListQuery::from($request));

        return new SupportTicketCollection($page);
    }

    public function store(OpenTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->open($request->user(), $request->validated());

        return (new SupportTicketResource($ticket))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Request $request, string $id): SupportTicketResource
    {
        $ticket = $this->tickets->find($request->user(), $id);

        return new SupportTicketResource($ticket);
    }

    public function messages(Request $request, string $id): SupportMessageCollection
    {
        $page = $this->tickets->messages($request->user(), $id, ListQuery::from($request));

        return new SupportMessageCollection($page);
    }

    public function reply(SupportReplyRequest $request, string $id): JsonResponse
    {
        $message = $this->tickets->reply(
            $request->user(),
            $id,
            $request->string('message')->toString(),
            $request->validated('attachment_url'),
        );

        return (new SupportMessageResource($message))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }
}
