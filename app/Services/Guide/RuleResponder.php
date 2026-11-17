<?php

namespace App\Services\Guide;

use App\Enums\GuideRoute;
use App\Enums\GuideUrgency;

/**
 * The guide's replies as rules, ported from the app's mock (`src/api/mocks/guideRules.ts`). It
 * names a specialty, a test or a screen, never a diagnosis or a drug to take.
 */
class RuleResponder implements GuideResponder
{
    public const SUGGESTIONS = [
        'I have a fever and headache',
        'Which test checks my blood sugar?',
        'Where can I buy paracetamol?',
        'Remind me to take my medicine',
    ];

    private const PHARMACY = ['\bbuy\b', 'purchase', '\border\b', 'pharmac', 'price of', 'how much (is|does|for)', 'drug store', 'chemist', 'refill'];

    private const MEDICATION = ['remind', 'add (my |a |the )?(medicine|medication|drug|tablet|pill)', 'schedule', '\bdose', 'missed', 'compliance', 'adherence', 'forgot to take', 'track my'];

    private const GREETING = ['^(hi|hello|hey|good (morning|afternoon|evening)|thanks|thank you|ok|okay)\b'];

    public function __construct(
        private readonly PlatformRules $platform,
        private readonly TestRules $tests,
        private readonly SymptomRules $symptoms,
        private readonly StoreSearch $store,
    ) {}

    public function opening(GuideContext $context): GuideReply
    {
        $name = $context->firstName === null ? '' : ', ' . $context->firstName;

        return new GuideReply(
            "Hello{$name}. Tell me what is wrong or what you are trying to do, and I will point you to the right doctor, test or medicine on 11med. "
                . 'I am not a doctor and cannot tell you what you have. If this is an emergency, call 112 now.',
            suggestions: self::SUGGESTIONS,
        );
    }

    public function reply(string $text, GuideContext $context): GuideReply
    {
        return $this->platform->early($text)
            ?? $this->pharmacy($text)
            ?? $this->test($text)
            ?? $this->symptom($text, $context)
            ?? $this->medication($text, $context)
            ?? $this->platform->profile($text)
            ?? $this->fallback($text);
    }

    private function pharmacy(string $text): ?GuideReply
    {
        $drug = $this->store->drugMentionedIn($text);
        $wantsToBuy = Patterns::any(self::PHARMACY, $text);
        $namedOnly = $drug !== null && ! $this->tests->asked($text) && ! Patterns::any(self::MEDICATION, $text);

        if (! $wantsToBuy && ! $namedOnly) {
            return null;
        }

        $query = $drug ?? $this->purchaseQuery($text);
        $body = $drug === null
            ? 'The store lists medicines from NAFDAC-licensed pharmacies, each with its own price and delivery. Anything needing a prescription says so before you buy.'
            : "The store lists {$drug} from more than one pharmacy, each with its own price and delivery. Every pharmacy on 11med is NAFDAC-licensed.";
        $action = $query === ''
            ? GuideAction::open(GuideRoute::Pharmacy, 'Open the store')
            : GuideAction::store($query, "Search the store for {$query}");

        return new GuideReply($body, [$action]);
    }

    private function purchaseQuery(string $text): string
    {
        $rest = preg_replace('~.*\b(buy|purchase|order|price of|how much (is|does|for))\b~u', '', $text);

        return trim((string) preg_replace('~[?.!]~u', '', (string) $rest));
    }

    private function test(string $text): ?GuideReply
    {
        if (! $this->tests->asked($text)) {
            return null;
        }

        $named = $this->tests->named($text);

        if ($named !== null) {
            return new GuideReply(
                'Laboratories on 11med price each test separately, and some will come to your home to take the sample. '
                    . 'Pick one and book a time. Every laboratory listed is MLSCN-licensed.',
                [GuideAction::test(...$named), GuideAction::open(GuideRoute::LabTests, 'Browse all tests')],
            );
        }

        return new GuideReply(
            'You can browse the tests laboratories run, with prices, or start from a laboratory near you. '
                . 'If a doctor has asked for tests, their request is on the Consult tab, ready to book.',
            [GuideAction::open(GuideRoute::LabTests, 'Browse tests'), GuideAction::open(GuideRoute::Labs, 'Find a laboratory')],
            suggestions: ['Which test checks my blood sugar?', 'I need a malaria test'],
        );
    }

    private function symptom(string $text, GuideContext $context): ?GuideReply
    {
        $rule = $this->symptoms->match($text);

        if ($rule === null) {
            return null;
        }

        $seeSoon = $this->symptoms->seeSoon($text);
        $test = $rule['test'] ?? $this->symptoms->generalTest($text);
        $known = isset($rule['condition'])
            && Patterns::any([$rule['condition']], mb_strtolower(implode("\n", $context->conditions)));

        $sentences = array_filter([
            $rule['says'],
            $known ? 'Your record already notes a related condition, which the doctor will see if you share your profile when booking.' : null,
            $seeSoon ? 'Since this has gone on a while or is getting worse, book someone today rather than waiting.' : null,
            'I cannot tell you what this is; a doctor can, and most are reachable within minutes.',
        ]);
        $actions = array_filter([
            GuideAction::specialty($rule['specialty'], $this->symptoms->label($rule['specialty'])),
            $test === null ? null : GuideAction::test(...$test),
        ]);

        return new GuideReply(
            implode(' ', $sentences),
            array_values($actions),
            $seeSoon ? GuideUrgency::SeeSoon : GuideUrgency::None,
        );
    }

    private function medication(string $text, GuideContext $context): ?GuideReply
    {
        if (! Patterns::any(self::MEDICATION, $text)) {
            return null;
        }

        if ($context->medicationCount > 0) {
            return new GuideReply(
                'Reminders come from your medication schedule. Add a medication and the app reminds you at each dose, and keeps your compliance score from what you log.',
                [GuideAction::open(GuideRoute::Medications, 'My medications'), GuideAction::open(GuideRoute::Compliance, 'See my compliance')],
            );
        }

        return new GuideReply(
            'Add a medication with its times and the app reminds you at each dose, even with no network. You can mark a dose taken from the notification itself.',
            [GuideAction::open(GuideRoute::AddMedication, 'Add a medication')],
        );
    }

    private function fallback(string $text): GuideReply
    {
        if (Patterns::any(self::GREETING, $text)) {
            return new GuideReply('Tell me what is wrong, or what you want to do, in your own words.', suggestions: self::SUGGESTIONS);
        }

        return new GuideReply(
            'I did not catch that. Describe what is wrong in a sentence, or tell me what you are trying to do: see a doctor, get a test, buy a medicine, or set a reminder.',
            [
                GuideAction::open(GuideRoute::Consult, 'See a doctor'),
                GuideAction::open(GuideRoute::LabTests, 'Get a test'),
                GuideAction::open(GuideRoute::Pharmacy, 'Buy a medicine'),
            ],
            suggestions: self::SUGGESTIONS,
        );
    }
}
