<?php

namespace Tests\Feature\Guide;

use App\Enums\GuideRoute;
use App\Enums\GuideUrgency;
use App\Enums\Role;
use App\Models\GuideConversation;
use App\Models\GuideMessage;
use App\Models\User;
use App\Services\Guide\GuideAction;
use App\Services\Guide\GuideContext;
use App\Services\Guide\GuideReply;
use App\Services\Guide\GuideResponder;
use App\Services\Identity\AccountEraser;
use Database\Seeders\LabCatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GuideConversationTest extends TestCase
{
    public function test_the_first_read_opens_a_conversation_that_names_112(): void
    {
        $patient = $this->patient('Adaeze Okafor');

        $conversation = $this->actingAs($patient)
            ->getJson('api/v1/guide/conversation')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.sender', 'guide')
            ->assertJsonPath('data.messages.0.urgency', 'none')
            ->assertJsonPath('data.messages.0.actions', [])
            ->assertJsonStructure(['data' => ['id', 'started_at', 'messages' => [['id', 'body', 'suggestions', 'sent_at']]]])
            ->json('data');

        $this->assertStringStartsWith('Hello, Adaeze.', $conversation['messages'][0]['body']);
        $this->assertStringContainsString('call 112', $conversation['messages'][0]['body']);

        $this->actingAs($patient)
            ->getJson('api/v1/guide/conversation')
            ->assertJsonPath('data.id', $conversation['id'])
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_a_message_returns_the_thread_with_the_reply(): void
    {
        $patient = $this->patient();

        $this->seed(LabCatalogueSeeder::class);

        $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => '  I have had a fever for three days  '])
            ->assertOk()
            ->assertJsonCount(3, 'data.messages')
            ->assertJsonPath('data.messages.1.sender', 'user')
            ->assertJsonPath('data.messages.1.body', 'I have had a fever for three days')
            ->assertJsonPath('data.messages.2.sender', 'guide')
            ->assertJsonPath('data.messages.2.urgency', 'see_soon')
            ->assertJsonCount(2, 'data.messages.2.actions')
            ->assertJson(['data' => ['messages' => [2 => ['actions' => [
                ['kind' => 'see_specialty', 'specialty' => 'general_practice', 'label' => 'See a general practitioner'],
                ['kind' => 'find_test', 'test_code' => 'MP', 'label' => 'Find a malaria test'],
            ]]]]]);
    }

    public function test_the_emergency_check_wins_over_everything_else(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => 'I want to book a cardiologist, I have chest pain'])
            ->assertOk()
            ->assertJsonPath('data.messages.2.urgency', 'emergency')
            ->assertJson(['data' => ['messages' => [2 => ['actions' => [['kind' => 'call', 'number' => '112', 'label' => 'Call 112']]]]]])
            ->assertJsonPath('data.messages.2.actions.1.route', 'emergency_qr');
    }

    public function test_a_responder_cannot_route_outside_the_allow_list_or_hide_the_emergency_line(): void
    {
        $this->app->instance(GuideResponder::class, new class implements GuideResponder
        {
            public function opening(GuideContext $context): GuideReply
            {
                return new GuideReply('Hello.');
            }

            public function reply(string $text, GuideContext $context): GuideReply
            {
                return new GuideReply('Go now.', [
                    ['kind' => 'open', 'route' => 'settings/delete-account', 'label' => 'Delete'],
                    ['kind' => 'call', 'number' => '08030000000', 'label' => 'Call me'],
                    ['kind' => 'see_specialty', 'specialty' => 'astrology', 'label' => 'Stars'],
                    GuideAction::open(GuideRoute::Consult, 'See a doctor'),
                ], GuideUrgency::Emergency);
            }
        });

        $this->actingAs($this->patient())
            ->postJson('api/v1/guide/conversation/messages', ['body' => 'something odd'])
            ->assertOk()
            ->assertJsonCount(2, 'data.messages.2.actions')
            ->assertJson(['data' => ['messages' => [2 => ['actions' => [
                ['kind' => 'call', 'number' => '112', 'label' => 'Call 112'],
                ['kind' => 'open', 'route' => 'consult', 'label' => 'See a doctor'],
            ]]]]]);
    }

    public function test_the_body_is_required_and_bounded(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => '   '])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.field', 'body');

        $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => str_repeat('a', 1001)])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'body');
    }

    public function test_messages_are_rate_limited(): void
    {
        config(['elevenmed.guide.messages_per_hour' => 2]);

        $patient = $this->patient();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($patient)->postJson('api/v1/guide/conversation/messages', ['body' => 'hello'])->assertOk();
        }

        $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => 'hello'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_bodies_are_encrypted_at_rest(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)->postJson('api/v1/guide/conversation/messages', ['body' => 'my stomach hurts'])->assertOk();

        $stored = DB::table('guide_messages')->pluck('body')->implode("\n");

        $this->assertStringNotContainsString('stomach', $stored);
    }

    public function test_a_conversation_keeps_only_its_newest_messages(): void
    {
        config(['elevenmed.guide.max_messages' => 4]);

        $patient = $this->patient();

        foreach (['hello', 'thanks', 'ok'] as $body) {
            $this->actingAs($patient)->postJson('api/v1/guide/conversation/messages', ['body' => $body])->assertOk();
        }

        $this->actingAs($patient)
            ->getJson('api/v1/guide/conversation')
            ->assertJsonCount(4, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'thanks')
            ->assertJsonPath('data.messages.2.body', 'ok');

        $this->assertSame(4, GuideMessage::count());
    }

    public function test_starting_over_deletes_the_conversation_for_real(): void
    {
        $patient = $this->patient();

        $first = $this->actingAs($patient)
            ->postJson('api/v1/guide/conversation/messages', ['body' => 'hello'])
            ->json('data.id');

        $this->actingAs($patient)
            ->deleteJson('api/v1/guide/conversation')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSame(0, GuideMessage::count());

        $this->actingAs($patient)
            ->getJson('api/v1/guide/conversation')
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.id', fn(string $id) => $id !== $first);
    }

    public function test_idle_conversations_are_pruned_and_active_ones_kept(): void
    {
        $idle = $this->patient();
        $active = $this->patient();

        $this->actingAs($idle)->getJson('api/v1/guide/conversation')->assertOk();
        $this->actingAs($active)->getJson('api/v1/guide/conversation')->assertOk();

        GuideConversation::where('user_id', $idle->id)->update(['last_message_at' => now()->subDays(31)]);

        $this->artisan('model:prune', ['--model' => [GuideConversation::class]])->assertSuccessful();

        $this->assertSame([$active->id], GuideConversation::pluck('user_id')->all());
        $this->assertSame(1, GuideMessage::count());
    }

    public function test_erasing_the_account_removes_the_conversation(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient)->postJson('api/v1/guide/conversation/messages', ['body' => 'hello'])->assertOk();

        app(AccountEraser::class)->erase($patient, 'Password1', null);

        $this->assertSame(0, GuideConversation::count());
        $this->assertSame(0, GuideMessage::count());
    }

    public function test_the_guide_is_for_patients_and_signed_in_accounts(): void
    {
        $doctor = User::factory()
            ->app(Role::Doctor)
            ->create();

        $this->actingAs($doctor)->getJson('api/v1/guide/conversation')->assertForbidden();
        $this->actingAs($doctor)->postJson('api/v1/guide/conversation/messages', ['body' => 'hi'])->assertForbidden();
        $this->actingAs($doctor)->deleteJson('api/v1/guide/conversation')->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson('api/v1/guide/conversation')->assertUnauthorized();
    }

    private function patient(string $name = 'Test Patient'): User
    {
        return User::factory()
            ->app(Role::Patient)
            ->create(['full_name' => $name]);
    }
}
