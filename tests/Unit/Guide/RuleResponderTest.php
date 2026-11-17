<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\EmergencyCheck;
use App\Services\Guide\GuideContext;
use App\Services\Guide\GuideReply;
use App\Services\Guide\Patterns;
use App\Services\Guide\PlatformRules;
use App\Services\Guide\RuleResponder;
use App\Services\Guide\StoreSearch;
use App\Services\Guide\SymptomRules;
use App\Services\Guide\TestRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuleResponderTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function emergencies(): array
    {
        return [
            'chest pain' => ['My chest pain started an hour ago'],
            'curly apostrophe' => ['I can’t breathe'],
            'seizure' => ['my son is having a SEIZURE'],
            'overdose' => ['I think I took an overdose'],
            'labour' => ['my waters have broke'],
        ];
    }

    #[DataProvider('emergencies')]
    public function test_emergencies_are_recognised(string $message): void
    {
        $this->assertTrue((new EmergencyCheck)->matches(Patterns::normalise($message)));
    }

    public function test_ordinary_messages_are_not_emergencies(): void
    {
        $this->assertFalse((new EmergencyCheck)->matches(Patterns::normalise('I have a headache')));
    }

    public function test_a_symptom_names_a_specialty_and_its_first_test(): void
    {
        $reply = $this->reply('my blood pressure has been high', ['Hypertension']);

        $this->assertSame('see_specialty', $reply->actions[0]['kind']);
        $this->assertSame('cardiology', $reply->actions[0]['specialty']);
        $this->assertSame('LIPID', $reply->actions[1]['test_code']);
        $this->assertStringContainsString('related condition', $reply->body);
    }

    public function test_a_named_test_finds_laboratories(): void
    {
        $reply = $this->reply('Which test checks my blood sugar?');

        $this->assertSame(['kind' => 'find_test', 'test_code' => 'HBA1C', 'label' => 'Find an HbA1c test'], $reply->actions[0]);
    }

    public function test_buying_searches_the_store(): void
    {
        $reply = $this->reply('Where can I buy paracetamol?');

        $this->assertSame(['kind' => 'search_pharmacy', 'query' => 'paracetamol', 'label' => 'Search the store for paracetamol'], $reply->actions[0]);
    }

    public function test_a_drug_the_store_lists_is_recognised_without_the_word_buy(): void
    {
        $store = new class implements StoreSearch
        {
            public function drugMentionedIn(string $text): ?string
            {
                return str_contains($text, 'amlodipine') ? 'Amlodipine' : null;
            }
        };

        $reply = $this->responder($store)->reply('amlodipine 5mg', new GuideContext(null, [], 0));

        $this->assertSame('Amlodipine', $reply->actions[0]['query']);
    }

    public function test_reminders_depend_on_whether_medications_are_tracked(): void
    {
        $this->assertSame('add_medication', $this->reply('remind me to take my tablets')->actions[0]['route']);
        $this->assertSame('medications', $this->reply('remind me to take my tablets', [], 2)->actions[0]['route']);
    }

    public function test_how_tos_open_one_screen(): void
    {
        $this->assertSame('caregivers', $this->reply('How do I add my mother as a caregiver?')->actions[0]['route']);
        $this->assertSame('emergency_qr', $this->reply('where is my QR code')->actions[0]['route']);
        $this->assertSame('profile', $this->reply('update my genotype please')->actions[0]['route']);
    }

    public function test_anything_else_offers_the_three_starting_points(): void
    {
        $reply = $this->reply('qwerty');

        $this->assertSame(['consult', 'lab_tests', 'pharmacy'], array_column($reply->actions, 'route'));
        $this->assertSame(RuleResponder::SUGGESTIONS, $reply->suggestions);
    }

    /**
     * @param  list<string>  $conditions
     */
    private function reply(string $message, array $conditions = [], int $medications = 0): GuideReply
    {
        $store = new class implements StoreSearch
        {
            public function drugMentionedIn(string $text): ?string
            {
                return null;
            }
        };

        return $this->responder($store)->reply(Patterns::normalise($message), new GuideContext('Ada', $conditions, $medications));
    }

    private function responder(StoreSearch $store): RuleResponder
    {
        return new RuleResponder(new PlatformRules, new TestRules, new SymptomRules, $store);
    }
}
