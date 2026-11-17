<?php

namespace App\Http\Controllers\Guide;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guide\SendGuideMessageRequest;
use App\Http\Resources\EmptyResource;
use App\Http\Resources\Guide\GuideConversationResource;
use App\Services\Guide\Guide;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function __construct(
        private readonly Guide $guide,
    ) {}

    public function show(Request $request): GuideConversationResource
    {
        $conversation = $this->guide->conversation($request->user());

        return new GuideConversationResource($conversation);
    }

    public function send(SendGuideMessageRequest $request): GuideConversationResource
    {
        $conversation = $this->guide->send($request->user(), $request->validated('body'));

        return new GuideConversationResource($conversation);
    }

    public function destroy(Request $request): EmptyResource
    {
        $this->guide->startOver($request->user());

        return new EmptyResource;
    }
}
