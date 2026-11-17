<?php

namespace App\Services\Guide;

use App\Enums\GuideUrgency;

/**
 * What the guide says back: prose, the structured actions the app may take, and the urgency.
 */
final readonly class GuideReply
{
    /**
     * @param  list<array<string, string>>  $actions  `zGuideAction`, built with `GuideAction`
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public string $body,
        public array $actions = [],
        public GuideUrgency $urgency = GuideUrgency::None,
        public array $suggestions = [],
    ) {}

    /**
     * @param  list<array<string, string>>  $actions
     */
    public function withActions(array $actions): self
    {
        return new self($this->body, $actions, $this->urgency, $this->suggestions);
    }
}
