<?php

namespace App\Services\Guide;

use Illuminate\Container\Attributes\Bind;

/**
 * Whatever writes the guide's replies. It runs only after the emergency check has passed, and
 * its actions are filtered by `ReplyGuard` before they are stored.
 */
#[Bind(RuleResponder::class)]
interface GuideResponder
{
    public function opening(GuideContext $context): GuideReply;

    /**
     * @param  string  $text  the message, lower-cased with its whitespace collapsed
     */
    public function reply(string $text, GuideContext $context): GuideReply;
}
