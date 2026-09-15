<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\User;
use App\Services\Fmcsa\CarrierRecord;
use App\Services\Fmcsa\FakeFmcsaClient;
use App\Services\Fmcsa\FmcsaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CarrierFmcsaVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var FakeFmcsaClient */
    protected $fmcsa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fmcsa = new FakeFmcsaClient();
        $this->app->instance(FmcsaClient::class, $this->fmcsa);
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name'                  => 'Boss',
            'email'                 => 'boss@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'carrier',
            'dot_number'            => '1234567',
            'privacy_accepted'      => true,
            'privacy_version'       => config('privacy.version'),
        ], $overrides);
    }

    public function test_sign_up_requires_accepting_the_privacy_notice()
    {
        $this->postJson('/api/auth/register', $this->payload(['privacy_accepted' => false]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('privacy_accepted');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_sign_up_records_the_consents_that_were_given()
    {
        $this->postJson('/api/auth/register', $this->payload(['sms_consent' => true]))->assertCreated();

        $user = User::first();

        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertSame(config('privacy.version'), $user->privacy_version);
        $this->assertNotNull($user->sms_consent_at);
        $this->assertNotNull($user->consent_ip);
    }

    public function test_sms_consent_is_not_implied_by_accepting_the_privacy_notice()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        // TCPA consent is separate and must be given explicitly.
        $this->assertNull(User::first()->sms_consent_at);
    }

    public function test_carrier_must_supply_an_mc_or_dot_number()
    {
        $this->postJson('/api/auth/register', $this->payload(['dot_number' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dot_number', 'mc_number']);
    }

    public function test_registration_is_rejected_when_fmcsa_has_no_such_carrier()
    {
        $this->fmcsa->stub('1234567', null);

        $this->postJson('/api/auth/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('dot_number');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('carriers', 0);
    }

    public function test_registration_is_rejected_when_the_carrier_is_not_allowed_to_operate()
    {
        $this->fmcsa->stub('1234567', CarrierRecord::fromArray([
            'dotNumber'        => '1234567',
            'legalName'        => 'OUT OF SERVICE LLC',
            'statusCode'       => 'I',
            'allowedToOperate' => false,
            'phone'            => '+15551230000',
        ]));

        $this->postJson('/api/auth/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('dot_number');

        // A rejected sign-up must leave nothing behind.
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_rejected_when_fmcsa_has_no_contact_to_send_the_code_to()
    {
        $this->fmcsa->stub('1234567', CarrierRecord::fromArray([
            'dotNumber'        => '1234567',
            'legalName'        => 'NO CONTACT LLC',
            'statusCode'       => 'A',
            'allowedToOperate' => true,
        ]));

        $this->postJson('/api/auth/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('dot_number');
    }

    public function test_the_company_name_comes_from_fmcsa_not_from_the_user()
    {
        $response = $this->postJson('/api/auth/register', $this->payload([
            'company_name' => 'NOMI O\'ZIM YOZDIM',
        ]))->assertCreated();

        $this->assertSame('TEST CARRIER 1234567 LLC', Carrier::first()->company_name);
        $this->assertSame('TEST CARRIER 1234567 LLC', $response->json('fmcsa.legal_name'));
    }

    public function test_the_code_channels_are_masked_fmcsa_contacts()
    {
        $response = $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $channels = collect($response->json('channels'));

        $this->assertEqualsCanonicalizing(['phone', 'email'], $channels->pluck('channel')->all());

        // The full contact is never returned.
        foreach ($channels as $channel) {
            $this->assertStringContainsString('*', $channel['masked']);
        }

        $this->assertStringNotContainsString(
            Carrier::first()->fmcsa_phone,
            json_encode($response->json())
        );
    }

    public function test_an_unverified_carrier_cannot_use_the_app()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Sanctum::actingAs(User::first());

        $this->getJson('/api/carrier/dashboard')
            ->assertStatus(403)
            ->assertJson(['code' => 'fmcsa_verification_required']);

        $this->getJson('/api/carrier/jobs')->assertStatus(403);
    }

    public function test_the_fmcsa_code_verifies_the_carrier_and_opens_the_app()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Sanctum::actingAs($user = User::first());

        $code = $this->postJson('/api/auth/carrier/send-code', ['channel' => 'phone'])
            ->assertOk()
            ->json('verification.debug_code');

        $this->postJson('/api/auth/carrier/verify-code', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.fmcsa_verified', true);

        $this->assertNotNull($user->carrier->fresh()->fmcsa_verified_at);
        $this->getJson('/api/carrier/dashboard')->assertOk();
    }

    public function test_a_wrong_fmcsa_code_does_not_verify_the_carrier()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Sanctum::actingAs($user = User::first());
        $this->postJson('/api/auth/carrier/send-code', ['channel' => 'email']);

        $this->postJson('/api/auth/carrier/verify-code', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertNull($user->carrier->fresh()->fmcsa_verified_at);
    }

    public function test_a_dot_number_cannot_be_registered_twice()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Sanctum::actingAs(User::first());
        $code = $this->postJson('/api/auth/carrier/send-code', ['channel' => 'phone'])->json('verification.debug_code');
        $this->postJson('/api/auth/carrier/verify-code', ['code' => $code])->assertOk();

        $this->postJson('/api/auth/register', $this->payload(['email' => 'second@test.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('dot_number');
    }

    public function test_a_carrier_whose_authority_lapses_loses_access()
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        Sanctum::actingAs($user = User::first());
        $code = $this->postJson('/api/auth/carrier/send-code', ['channel' => 'phone'])->json('verification.debug_code');
        $this->postJson('/api/auth/carrier/verify-code', ['code' => $code])->assertOk();

        $user->carrier->forceFill(['allowed_to_operate' => false])->save();

        $this->getJson('/api/carrier/dashboard')
            ->assertStatus(403)
            ->assertJson(['code' => 'fmcsa_not_active']);
    }

    public function test_a_blocked_user_cannot_log_in_or_use_existing_tokens()
    {
        $user = User::create([
            'name' => 'Blocked', 'email' => 'blocked@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Test'])->save();

        $this->postJson('/api/auth/login', ['email' => 'blocked@test.com', 'password' => 'password'])
            ->assertStatus(403)
            ->assertJson(['code' => 'account_blocked']);

        Sanctum::actingAs($user);
        $this->getJson('/api/driver/profile')
            ->assertStatus(403)
            ->assertJson(['code' => 'account_blocked']);
    }
}
