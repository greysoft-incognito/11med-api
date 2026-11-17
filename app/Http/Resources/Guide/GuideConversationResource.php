<?php

namespace App\Http\Resources\Guide;

use App\Enums\HttpStatus;
use App\Http\Resources\ApiResource;
use App\Models\GuideConversation;
use App\Models\GuideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `zGuideConversation`: the whole thread, never cached, and 200 even on the read that starts it.
 *
 * @property GuideConversation $resource
 */
class GuideConversationResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->uuid,
            'messages' => $this->resource->messages->map(fn(GuideMessage $message) => [
                'id' => $message->uuid,
                'sender' => $message->sender->value,
                'body' => $message->body,
                'actions' => $message->actions,
                'urgency' => $message->urgency->value,
                'suggestions' => $message->suggestions,
                'sent_at' => $this->iso($message->sent_at),
            ])->all(),
            'started_at' => $this->iso($this->resource->started_at),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(HttpStatus::OK->value);
        $response->headers->set('Cache-Control', 'no-store');
    }
}
