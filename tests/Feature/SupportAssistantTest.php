<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\Message;
use App\Models\User;
use App\Services\Support\RuleBasedResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function driver(string $email = 'jasur@test.com'): User
    {
        $user = User::create([
            'name' => 'Jasur Driver', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        DriverProfile::create(['user_id' => $user->id, 'first_name' => 'Jasur', 'last_name' => 'Driver']);

        return $user->fresh();
    }

    protected function ask(string $question)
    {
        return $this->postJson('/api/support', ['body' => $question])->assertOk();
    }

    protected function lastReply(): array
    {
        $message = Message::where('author_type', Message::AUTHOR_AI)->latest('id')->first();

        return ['body' => (string) $message->body, 'resolved' => (bool) $message->resolved_question];
    }

    // ------------------------------------------------------------------
    // It answers, rather than passing things on
    // ------------------------------------------------------------------

    public function test_it_answers_what_this_website_is()
    {
        Sanctum::actingAs($this->driver());

        $response = $this->ask('what about is this website?');

        $this->assertFalse($response->json('conversation.escalated'));
        $this->assertStringContainsString('CDL', $this->lastReply()['body']);
    }

    /** @dataProvider generalQuestions */
    public function test_general_questions_are_answered_without_a_handover(string $question)
    {
        Sanctum::actingAs($this->driver());

        $response = $this->ask($question);

        $this->assertFalse(
            $response->json('conversation.escalated'),
            "Handed over on: {$question}"
        );
        $this->assertTrue($this->lastReply()['resolved'], "Marked unresolved: {$question}");
    }

    public function generalQuestions(): array
    {
        return [
            ['what is this site for?'],
            ['how does it work?'],
            ['how do i get started?'],
            ['how much does it cost'],
            ['why do you need my cdl number'],
            ['my dot number says not found'],
            ['how are drivers ranked'],
            ['what is an mvr'],
            ['what happens after i hire someone'],
            ['i want to leave a review'],
            ['do you book flights'],
            ['hello'],
            ['something completely unrelated to trucking'],
        ];
    }

    public function test_an_unrecognised_question_still_gets_an_orientation_answer()
    {
        Sanctum::actingAs($this->driver());

        $this->ask('what colour is the sky');

        $reply = $this->lastReply();

        $this->assertTrue($reply['resolved']);
        $this->assertStringContainsString('DriverHub', $reply['body']);
    }

    public function test_the_answer_is_tailored_to_the_role_asking()
    {
        Sanctum::actingAs($this->driver());
        $this->ask('how do i get started?');
        $driverAnswer = $this->lastReply()['body'];

        $carrier = User::create([
            'name' => 'Boss', 'email' => 'boss@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_CARRIER,
        ]);
        \App\Models\Carrier::create(['user_id' => $carrier->id, 'company_name' => 'Test']);

        Sanctum::actingAs($carrier->fresh());
        $this->ask('how do i get started?');
        $carrierAnswer = $this->lastReply()['body'];

        $this->assertNotSame($driverAnswer, $carrierAnswer);
        $this->assertStringContainsString('Profile', $driverAnswer);
        $this->assertStringContainsString('FMCSA', $carrierAnswer);
    }

    // ------------------------------------------------------------------
    // It hands over when it genuinely should
    // ------------------------------------------------------------------

    /** @dataProvider humanQuestions */
    public function test_questions_that_need_a_person_hand_over_immediately(string $question)
    {
        Sanctum::actingAs($this->driver());

        $this->assertTrue(
            $this->ask($question)->json('conversation.escalated'),
            "Did not hand over on: {$question}"
        );
    }

    public function humanQuestions(): array
    {
        return [
            ['i want a refund'],
            ['i was charged twice'],
            ['let me talk to a human'],
            ['please delete my account'],
            ['why am i blacklisted'],
        ];
    }

    public function test_answering_several_questions_does_not_trigger_a_handover()
    {
        Sanctum::actingAs($this->driver());

        foreach (['what is this site', 'how does it work', 'how much does it cost', 'how are drivers ranked'] as $q) {
            $response = $this->ask($q);
        }

        // Four answered questions used to be enough to fetch a person.
        $this->assertFalse($response->json('conversation.escalated'));
        $this->assertSame(0, Message::where('author_type', Message::AUTHOR_SYSTEM)->count());
    }

    public function test_an_answered_question_resets_the_unanswered_run()
    {
        Sanctum::actingAs($user = $this->driver());

        $conversation = app(\App\Services\SupportService::class)->openFor($user);
        $support = app(\App\Services\SupportService::class);

        // Two failures, then a success, then two more failures: never three in a row.
        foreach ([false, false, true, false, false] as $shouldResolve) {
            $conversation->messages()->create([
                'author_type'       => Message::AUTHOR_AI,
                'author_name'       => 'Assistant',
                'body'              => 'x',
                'resolved_question' => $shouldResolve,
            ]);
        }

        $this->assertFalse($conversation->fresh()->isEscalated());
    }

    // ------------------------------------------------------------------
    // Nothing is lost when Telegram is not configured
    // ------------------------------------------------------------------

    public function test_a_handover_without_telegram_says_so_honestly()
    {
        config(['support.telegram.bot_token' => null, 'support.telegram.chat_id' => null]);

        Sanctum::actingAs($this->driver());
        $this->ask('i want a refund');

        $system = Message::where('author_type', Message::AUTHOR_SYSTEM)->latest('id')->first();

        $this->assertStringContainsString('support queue', $system->body);
        $this->assertStringNotContainsString('agent', $system->body);
    }

    public function test_an_admin_sees_escalated_conversations_and_can_reply()
    {
        Sanctum::actingAs($driver = $this->driver());
        $this->ask('i want a refund');

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        Sanctum::actingAs($admin);

        $queue = $this->getJson('/api/admin/support?status=escalated')->assertOk()->json('conversations');

        $this->assertCount(1, $queue);
        $this->assertSame($driver->email, $queue[0]['user']['email']);
        $this->assertTrue($queue[0]['waiting'] === false || $queue[0]['waiting'] === true);

        $conversationId = $queue[0]['id'];

        $this->postJson("/api/admin/support/{$conversationId}/reply", [
            'body' => 'Checked your account — the charge was refunded this morning.',
        ])->assertOk();

        // The user sees it in their own widget.
        Sanctum::actingAs($driver);

        $messages = collect($this->getJson('/api/support')->json('messages'));
        $agent = $messages->firstWhere('author_type', Message::AUTHOR_AGENT);

        $this->assertNotNull($agent);
        $this->assertStringContainsString('refunded', $agent['body']);
    }

    public function test_non_admins_cannot_read_the_support_queue()
    {
        Sanctum::actingAs($this->driver());

        $this->getJson('/api/admin/support')->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // The responder itself
    // ------------------------------------------------------------------

    public function test_the_responder_picks_the_best_matching_topic_not_the_first()
    {
        $responder = new RuleBasedResponder();

        // "code" appears in the plans topic too; the codes topic should win.
        $answer = $responder->answer([['role' => 'user', 'content' => 'i did not receive my code']]);

        $this->assertTrue($answer['resolved']);
        $this->assertStringContainsString('15 minutes', $answer['reply']);
    }

    public function test_an_empty_question_is_handled()
    {
        $responder = new RuleBasedResponder();

        $this->assertTrue($responder->answer([['role' => 'user', 'content' => '   ']])['resolved']);
    }
}
