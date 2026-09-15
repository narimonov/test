<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use App\Services\Support\AiResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['documents.disk' => 'local', 'payments.default' => 'fake']);
    }

    protected function carrier(string $plan = 'growth', string $email = 'boss@test.com'): User
    {
        $user = User::create([
            'name' => 'Boss', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_CARRIER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Carrier::create(['user_id' => $user->id, 'company_name' => 'Test Carrier'])
            ->forceFill([
                'fmcsa_verified_at'       => now(),
                'allowed_to_operate'      => true,
                'subscription_plan'       => $plan,
                'subscription_status'     => 'active',
                'subscription_expires_at' => now()->addMonth(),
            ])->save();

        return $user->fresh();
    }

    protected function driver(string $email = 'jasur@test.com'): User
    {
        $user = User::create([
            'name' => 'Jasur Driver', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        DriverProfile::create([
            'user_id' => $user->id, 'first_name' => 'Jasur', 'last_name' => 'Driver',
            'cdl_class' => 'A', 'cdl_expires_at' => now()->addYears(3), 'years_experience' => 6,
            'city' => 'Chicago', 'state' => 'IL', 'work_authorization' => 'us_citizen',
        ]);

        return $user->fresh();
    }

    // ------------------------------------------------------------------
    // Plan limits
    // ------------------------------------------------------------------

    public function test_starter_is_capped_at_three_active_job_posts()
    {
        Sanctum::actingAs($user = $this->carrier('starter'));

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/carrier/jobs', ['title' => "Job {$i}"])->assertCreated();
        }

        $this->postJson('/api/carrier/jobs', ['title' => 'One too many'])
            ->assertStatus(402)
            ->assertJson(['code' => 'plan_limit_reached']);

        $this->assertSame(3, JobPost::count());
    }

    public function test_growth_has_no_job_post_limit()
    {
        Sanctum::actingAs($this->carrier('growth'));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/carrier/jobs', ['title' => "Job {$i}"])->assertCreated();
        }

        $this->assertSame(5, JobPost::count());
    }

    public function test_starter_cannot_tune_the_scoring_weights_or_add_drivers()
    {
        Sanctum::actingAs($this->carrier('starter'));

        $this->putJson('/api/carrier/scoring/overrides', [
            'criteria' => [['key' => 'years_experience', 'weight' => 5]],
        ])->assertStatus(402)->assertJson(['code' => 'upgrade_required']);

        $this->postJson('/api/carrier/drivers', [
            'first_name' => 'Manual', 'last_name' => 'Entry', 'cdl_class' => 'A',
        ])->assertStatus(402)->assertJson(['code' => 'upgrade_required']);
    }

    public function test_starter_results_are_capped_but_growth_results_are_not()
    {
        for ($i = 0; $i < 30; $i++) {
            DriverProfile::create([
                'first_name' => "Driver{$i}", 'last_name' => 'Test', 'cdl_class' => 'A',
                'cdl_expires_at' => now()->addYear(), 'years_experience' => 5,
            ]);
        }

        Sanctum::actingAs($this->carrier('starter'));
        $starter = $this->getJson('/api/carrier/drivers?per_page=100')->assertOk();

        $this->assertTrue($starter->json('meta.capped_by_plan'));
        $this->assertSame(25, $starter->json('meta.total'));

        Sanctum::actingAs($this->carrier('growth', 'second@test.com'));
        $growth = $this->getJson('/api/carrier/drivers?per_page=100')->assertOk();

        $this->assertFalse($growth->json('meta.capped_by_plan'));
        $this->assertSame(30, $growth->json('meta.total'));
    }

    public function test_personal_recruiting_is_pro_only()
    {
        Sanctum::actingAs($this->carrier('growth'));

        $this->postJson('/api/carrier/recruiting-requests', [
            'title' => '5 OTR drivers', 'brief' => str_repeat('a', 40),
        ])->assertStatus(402)->assertJson(['code' => 'upgrade_required']);

        Sanctum::actingAs($this->carrier('pro', 'pro@test.com'));

        $this->postJson('/api/carrier/recruiting-requests', [
            'title' => '5 OTR drivers', 'brief' => str_repeat('a', 40),
        ])->assertCreated();

        // Each request opens its own thread with the recruiter.
        $this->assertDatabaseCount('recruiting_requests', 1);
        $this->assertSame(1, Conversation::where('type', Conversation::TYPE_RECRUITING)->count());
    }

    // ------------------------------------------------------------------
    // Chat
    // ------------------------------------------------------------------

    public function test_starter_can_only_message_drivers_who_applied()
    {
        $carrierUser = $this->carrier('starter');
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($carrierUser);

        $this->postJson('/api/carrier/conversations', ['driver_profile_id' => $driver->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_profile_id');

        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR']);
        Application::create(['job_post_id' => $job->id, 'driver_profile_id' => $driver->id]);

        $this->postJson('/api/carrier/conversations', ['driver_profile_id' => $driver->id])
            ->assertCreated();
    }

    public function test_growth_can_message_any_driver()
    {
        Sanctum::actingAs($this->carrier('growth'));

        $this->postJson('/api/carrier/conversations', [
            'driver_profile_id' => $this->driver()->driverProfile->id,
            'body'              => 'We have a reefer lane out of Chicago.',
        ])->assertCreated();

        $this->assertSame(1, Message::count());
    }

    public function test_a_driver_without_an_account_cannot_be_messaged()
    {
        Sanctum::actingAs($this->carrier('growth'));

        // Added by a recruiter, so there is no account behind the profile.
        $driver = DriverProfile::create([
            'first_name' => 'Manual', 'last_name' => 'Entry', 'cdl_class' => 'A',
            'phone' => '+15551230000', 'source' => 'manual',
        ]);

        $this->postJson('/api/carrier/conversations', ['driver_profile_id' => $driver->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_profile_id');

        $this->assertSame(0, Conversation::count());
    }

    public function test_both_sides_see_the_same_thread()
    {
        $carrierUser = $this->carrier('growth');
        $driverUser = $this->driver();

        Sanctum::actingAs($carrierUser);
        $conversationId = $this->postJson('/api/carrier/conversations', [
            'driver_profile_id' => $driverUser->driverProfile->id,
            'body'              => 'Are you available next week?',
        ])->assertCreated()->json('conversation.id');

        Sanctum::actingAs($driverUser);

        $this->getJson("/api/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Are you available next week?');

        $this->postJson("/api/conversations/{$conversationId}/messages", ['body' => 'Yes, I am.'])
            ->assertCreated();

        Sanctum::actingAs($carrierUser);
        $this->assertCount(2, $this->getJson("/api/conversations/{$conversationId}")->json('messages'));
    }

    public function test_an_outsider_cannot_read_a_thread()
    {
        $carrierUser = $this->carrier('growth');
        $driverUser = $this->driver();

        Sanctum::actingAs($carrierUser);
        $conversationId = $this->postJson('/api/carrier/conversations', [
            'driver_profile_id' => $driverUser->driverProfile->id,
            'body'              => 'Private.',
        ])->json('conversation.id');

        Sanctum::actingAs($this->driver('someone.else@test.com'));

        $this->getJson("/api/conversations/{$conversationId}")->assertStatus(403);
    }

    public function test_a_message_can_carry_a_pdf_that_only_participants_can_open()
    {
        $carrierUser = $this->carrier('growth');
        $driverUser = $this->driver();

        Sanctum::actingAs($carrierUser);
        $conversationId = $this->postJson('/api/carrier/conversations', [
            'driver_profile_id' => $driverUser->driverProfile->id,
        ])->json('conversation.id');

        $response = $this->post("/api/conversations/{$conversationId}/messages", [
            'body'        => 'Here is the packet.',
            'attachments' => [UploadedFile::fake()->create('packet.pdf', 60, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $attachmentId = $response->json('message.attachments.0.id');

        // The storage path is never exposed.
        $this->assertArrayNotHasKey('path', $response->json('message.attachments.0'));

        Sanctum::actingAs($driverUser);
        $this->get("/api/attachments/{$attachmentId}")->assertOk();

        Sanctum::actingAs($this->driver('nosy@test.com'));
        $this->get("/api/attachments/{$attachmentId}")->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Matching
    // ------------------------------------------------------------------

    public function test_matches_exclude_applicants_hired_and_blacklisted_drivers()
    {
        $carrierUser = $this->carrier('growth');
        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR']);

        $available = $this->driver('available@test.com')->driverProfile;
        $applied = $this->driver('applied@test.com')->driverProfile;
        $hired = $this->driver('hired@test.com')->driverProfile;
        $blacklisted = $this->driver('blacklisted@test.com')->driverProfile;

        Application::create(['job_post_id' => $job->id, 'driver_profile_id' => $applied->id]);
        $hired->forceFill(['hired_carrier_id' => $carrierUser->carrier->id, 'hired_at' => now()])->save();
        $blacklisted->forceFill(['blacklisted_at' => now()])->save();

        Sanctum::actingAs($carrierUser);

        $ids = collect($this->getJson("/api/carrier/jobs/{$job->id}/matches")->assertOk()->json('matches'))
            ->pluck('driver.id');

        $this->assertTrue($ids->contains($available->id));
        $this->assertFalse($ids->contains($applied->id));
        $this->assertFalse($ids->contains($hired->id));
        $this->assertFalse($ids->contains($blacklisted->id));
    }

    public function test_matches_respect_the_jobs_own_requirements()
    {
        $carrierUser = $this->carrier('growth');

        $job = JobPost::create([
            'carrier_id'   => $carrierUser->carrier->id,
            'title'        => 'Experienced only',
            'requirements' => ['knockouts' => [
                ['key' => 'years_experience', 'operator' => 'gte', 'value' => 10, 'reason' => 'Needs 10 years'],
            ]],
        ]);

        $this->driver('green@test.com');   // 6 years, below the bar

        Sanctum::actingAs($carrierUser);

        $this->assertSame(0, $this->getJson("/api/carrier/jobs/{$job->id}/matches")->json('summary.matched'));
    }

    public function test_a_carrier_cannot_see_matches_for_someone_elses_job()
    {
        $other = $this->carrier('growth', 'other@test.com');
        $job = JobPost::create(['carrier_id' => $other->carrier->id, 'title' => 'Not yours']);

        Sanctum::actingAs($this->carrier('growth'));

        $this->getJson("/api/carrier/jobs/{$job->id}/matches")->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Billing
    // ------------------------------------------------------------------

    public function test_checkout_creates_a_pending_payment_and_a_redirect()
    {
        Sanctum::actingAs($this->carrier('starter'));

        $response = $this->postJson('/api/carrier/checkout', ['plan' => 'pro', 'provider' => 'fake'])
            ->assertCreated();

        $payment = Payment::first();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(50000, $payment->amount_cents);
        $this->assertNotEmpty($response->json('redirect_url'));
    }

    public function test_confirming_a_payment_activates_the_plan()
    {
        Sanctum::actingAs($user = $this->carrier('starter'));

        $reference = $this->postJson('/api/carrier/checkout', ['plan' => 'pro', 'provider' => 'fake'])
            ->json('payment.provider_reference');

        $this->postJson('/api/carrier/checkout/confirm', ['reference' => $reference])->assertOk();

        $carrier = $user->carrier->fresh();

        $this->assertSame('pro', $carrier->subscription_plan);
        $this->assertTrue($carrier->has_active_subscription);
        $this->assertSame(Payment::STATUS_PAID, Payment::first()->status);
    }

    public function test_a_payment_belonging_to_another_carrier_cannot_be_confirmed()
    {
        Sanctum::actingAs($this->carrier('starter'));
        $reference = $this->postJson('/api/carrier/checkout', ['plan' => 'pro', 'provider' => 'fake'])
            ->json('payment.provider_reference');

        Sanctum::actingAs($this->carrier('starter', 'thief@test.com'));

        $this->postJson('/api/carrier/checkout/confirm', ['reference' => $reference])->assertStatus(404);
    }

    public function test_an_unknown_plan_is_rejected()
    {
        Sanctum::actingAs($this->carrier('starter'));

        $this->postJson('/api/carrier/checkout', ['plan' => 'platinum'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    // ------------------------------------------------------------------
    // Support
    // ------------------------------------------------------------------

    public function test_the_assistant_answers_a_question_it_knows()
    {
        Sanctum::actingAs($this->driver());

        $response = $this->postJson('/api/support', ['body' => 'How much does the Pro plan cost?'])
            ->assertOk();

        $messages = collect($response->json('messages'));

        $this->assertStringContainsString('$500', $messages->last()['body']);
        $this->assertFalse($response->json('conversation.escalated'));
    }

    public function test_a_question_it_cannot_answer_is_handed_to_a_person()
    {
        Sanctum::actingAs($this->driver());

        $response = $this->postJson('/api/support', ['body' => 'Please wire my settlement to a new bank account'])
            ->assertOk();

        $this->assertTrue($response->json('conversation.escalated'));
        $this->assertSame(
            1,
            Message::where('author_type', Message::AUTHOR_SYSTEM)->count()
        );
    }

    public function test_a_user_can_ask_for_a_person_directly()
    {
        Sanctum::actingAs($this->driver());

        $this->getJson('/api/support')->assertOk();
        $this->postJson('/api/support/escalate')->assertOk()
            ->assertJsonPath('conversation.escalated', true);
    }

    public function test_an_agent_reply_from_telegram_lands_in_the_users_chat()
    {
        config(['support.telegram.webhook_secret' => 'shhh']);

        Sanctum::actingAs($this->driver());
        $conversationId = $this->postJson('/api/support/escalate')->json('conversation.id');

        $this->postJson('/api/telegram/webhook', [
            'message' => [
                'message_id' => 42,
                'text'       => 'Checked your account — the code is on its way.',
                'from'       => ['first_name' => 'Aziz'],
                'reply_to_message' => ['text' => "Support handover — #{$conversationId}"],
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'shhh'])->assertOk();

        Sanctum::actingAs(User::where('role', User::ROLE_DRIVER)->first());

        $messages = collect($this->getJson('/api/support')->json('messages'));
        $agentMessage = $messages->firstWhere('author_type', Message::AUTHOR_AGENT);

        $this->assertNotNull($agentMessage);
        $this->assertSame('Aziz', $agentMessage['author_name']);
    }

    public function test_the_telegram_webhook_rejects_a_wrong_secret()
    {
        config(['support.telegram.webhook_secret' => 'shhh']);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => 'hi']], [
            'X-Telegram-Bot-Api-Secret-Token' => 'guess',
        ])->assertStatus(403);
    }

    public function test_the_telegram_webhook_is_shut_when_no_secret_is_configured()
    {
        config(['support.telegram.webhook_secret' => null]);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => 'hi']])->assertStatus(403);
    }

    public function test_the_assistant_is_silent_once_an_agent_has_taken_over()
    {
        Sanctum::actingAs($driver = $this->driver());

        $this->postJson('/api/support/escalate')->assertOk();

        $before = Message::where('author_type', Message::AUTHOR_AI)->count();

        $this->postJson('/api/support', ['body' => 'How much does the Pro plan cost?'])->assertOk();

        $this->assertSame($before, Message::where('author_type', Message::AUTHOR_AI)->count());
    }
}
