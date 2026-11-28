<?php

namespace App\Http\Controllers\Support;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Paging\ListQuery;
use App\Http\Requests\Support\AssignTicketRequest;
use App\Http\Requests\Support\SupportQueueRequest;
use App\Http\Requests\Support\SupportReplyRequest;
use App\Http\Resources\Support\AdminSupportTicketCollection;
use App\Http\Resources\Support\AdminSupportTicketResource;
use App\Http\Resources\Support\SupportMessageCollection;
use App\Http\Resources\Support\SupportMessageResource;
use App\Services\Support\SupportQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The team's support queue.
 */
class AdminSupportController extends Controller
{
    public function __construct(
        private readonly SupportQueue $queue,
    ) {}

    public function index(SupportQueueRequest $request): AdminSupportTicketCollection
    {
        $page = $this->queue->list($request->user(), $request->validated(), ListQuery::from($request));

        return new AdminSupportTicketCollection($page);
    }

    public function show(string $id): AdminSupportTicketResource
    {
        $ticket = $this->queue->find($id);

        return new AdminSupportTicketResource($ticket);
    }

    public function messages(Request $request, string $id): SupportMessageCollection
    {
        $page = $this->queue->messages($id, ListQuery::from($request));

        return new SupportMessageCollection($page);
    }

    public function assign(AssignTicketRequest $request, string $id): AdminSupportTicketResource
    {
        $ticket = $this->queue->assign($request->user(), $id, $request->validated('assignee_id'));

        return new AdminSupportTicketResource($ticket);
    }

    public function reply(SupportReplyRequest $request, string $id): JsonResponse
    {
        $message = $this->queue->reply($request->user(), $id, $request->string('message')->toString());

        return (new SupportMessageResource($message))
            ->response()
            ->setStatusCode(HttpStatus::CREATED->value);
    }

    public function close(Request $request, string $id): AdminSupportTicketResource
    {
        $ticket = $this->queue->close($request->user(), $id);

        return new AdminSupportTicketResource($ticket);
    }
}
