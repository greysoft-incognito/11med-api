<?php

namespace App\Services\Guide;

use App\Enums\GuideSender;
use App\Enums\GuideUrgency;
use App\Models\GuideConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The guide's conversation (app handover §11): one per patient, kept until they start over or it
 * sits idle past the retention period, and never more than the newest messages the config allows.
 * The emergency check runs before the responder and its answer stands.
 */
class Guide
{
    public function __construct(
        private readonly EmergencyCheck $emergency,
        private readonly GuideResponder $responder,
        private readonly ReplyGuard $guard,
        private readonly GuideContextReader $context,
    ) {}

    public function conversation(User $patient): GuideConversation
    {
        $conversation = $this->of($patient)->first()
            ?? DB::transaction(fn() => $this->start($patient));

        return $this->withMessages($conversation);
    }

    public function send(User $patient, string $body): GuideConversation
    {
        $text = Patterns::normalise($body);
        $reply = $this->emergency->matches($text)
            ? $this->emergency->reply()
            : $this->responder->reply($text, $this->context->for($patient));

        $conversation = DB::transaction(function () use ($patient, $body, $reply) {
            $conversation = $this->of($patient)->lockForUpdate()->first() ?? $this->start($patient);

            $this->append($conversation, GuideSender::User, new GuideReply($body));
            $this->append($conversation, GuideSender::Guide, $this->guard->check($reply));
            $this->trim($conversation);

            return $conversation;
        });

        return $this->withMessages($conversation);
    }

    public function startOver(User $patient): void
    {
        $this->of($patient)->delete();
    }

    private function start(User $patient): GuideConversation
    {
        $patient->newQuery()->lockForUpdate()->find($patient->id);

        $existing = $this->of($patient)->first();

        if ($existing !== null) {
            return $existing;
        }

        $conversation = GuideConversation::create([
            'user_id' => $patient->id,
            'started_at' => now(),
            'last_message_at' => now(),
        ]);

        $opening = $this->responder->opening($this->context->for($patient));

        $this->append($conversation, GuideSender::Guide, $this->guard->check($opening));

        return $conversation;
    }

    private function append(GuideConversation $conversation, GuideSender $sender, GuideReply $reply): void
    {
        $user = $sender === GuideSender::User;

        $conversation->messages()->create([
            'sender' => $sender,
            'body' => $reply->body,
            'actions' => $user ? [] : $reply->actions,
            'urgency' => $user ? GuideUrgency::None : $reply->urgency,
            'suggestions' => $user ? [] : $reply->suggestions,
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
    }

    private function trim(GuideConversation $conversation): void
    {
        $oldestKept = $conversation->messages()
            ->orderByDesc('id')
            ->skip(config('elevenmed.guide.max_messages') - 1)
            ->value('id');

        if ($oldestKept !== null) {
            $conversation->messages()
                ->where('id', '<', $oldestKept)
                ->delete();
        }
    }

    private function of(User $patient): Builder
    {
        return GuideConversation::query()->where('user_id', $patient->id);
    }

    private function withMessages(GuideConversation $conversation): GuideConversation
    {
        return $conversation->load(['messages' => fn($query) => $query->orderBy('id')]);
    }
}
