<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function carrier(bool $subscribed = true, bool $verified = true): User
    {
        $user = User::create([
            'name'     => 'Recruiter',
            'email'    => 'recruiter@test.com',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_CARRIER,
        ]);

        // email_verified_at fillable emas (mass assignment bilan tasdiqlab bo'lmaydi).
        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Carrier::create([
            'user_id'                 => $user->id,
            'company_name'            => 'Test Carrier',
            'subscription_plan'       => $subscribed ? 'pro' : 'free',
            'subscription_status'     => $subscribed ? 'active' : 'inactive',
            'subscription_expires_at' => $subscribed ? now()->addMonth() : null,
        ])->forceFill([
            // FMCSA tekshiruvi alohida test faylida sinaladi.
            'fmcsa_verified_at'  => now(),
            'allowed_to_operate' => true,
        ])->save();

        return $user;
    }

    protected function driverUser(bool $verified = true): User
    {
        $user = User::create([
            'name'     => 'Jasur Driver',
            'email'    => 'jasur@test.com',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_DRIVER,
        ]);

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        DriverProfile::create([
            'user_id'          => $user->id,
            'first_name'       => 'Jasur',
            'last_name'        => 'Driver',
            'cdl_class'        => 'A',
            'cdl_expires_at'   => now()->addYears(3),
            'years_experience' => 5,
        ]);

        return $user;
    }

    public function test_registration_creates_a_driver_profile_and_returns_a_token()
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Yangi Driver',
            'email'                 => 'yangi@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'driver',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'role', 'is_verified']]);

        $this->assertDatabaseHas('driver_profiles', ['email' => 'yangi@test.com']);
    }

    public function test_registration_creates_a_carrier_record()
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Boss',
            'email'                 => 'boss@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'carrier',
            'dot_number'            => '1234567',
        ])->assertCreated();

        // Kompaniya nomi FMCSA'dan olinadi, foydalanuvchidan emas.
        $this->assertDatabaseHas('carriers', ['dot_number' => '1234567']);
    }

    public function test_carrier_registration_requires_an_mc_or_dot_number()
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Boss',
            'email'                 => 'boss@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'carrier',
        ])->assertStatus(422)->assertJsonValidationErrors(['dot_number', 'mc_number']);
    }

    public function test_unverified_driver_cannot_apply()
    {
        $carrier = $this->carrier();
        $job = JobPost::create(['carrier_id' => $carrier->carrier->id, 'title' => 'OTR Driver']);

        Sanctum::actingAs($this->driverUser(false));

        $this->postJson("/api/driver/jobs/{$job->id}/apply")
            ->assertStatus(403)
            ->assertJson(['code' => 'verification_required']);
    }

    public function test_verified_driver_can_apply_and_score_is_snapshotted()
    {
        $carrier = $this->carrier();
        $job = JobPost::create(['carrier_id' => $carrier->carrier->id, 'title' => 'OTR Driver']);

        Sanctum::actingAs($this->driverUser());

        $this->postJson("/api/driver/jobs/{$job->id}/apply", ['cover_note' => 'Tayyorman'])
            ->assertCreated();

        $this->assertDatabaseCount('applications', 1);
        $this->assertNotNull(\App\Models\Application::first()->score);
    }

    public function test_driver_cannot_apply_twice_to_the_same_job()
    {
        $carrier = $this->carrier();
        $job = JobPost::create(['carrier_id' => $carrier->carrier->id, 'title' => 'OTR Driver']);

        Sanctum::actingAs($this->driverUser());

        $this->postJson("/api/driver/jobs/{$job->id}/apply")->assertCreated();
        $this->postJson("/api/driver/jobs/{$job->id}/apply")->assertStatus(422);

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_driver_does_not_see_the_internal_score_on_their_applications()
    {
        $carrier = $this->carrier();
        $job = JobPost::create(['carrier_id' => $carrier->carrier->id, 'title' => 'OTR Driver']);

        Sanctum::actingAs($this->driverUser());
        $this->postJson("/api/driver/jobs/{$job->id}/apply")->assertCreated();

        $response = $this->getJson('/api/driver/applications')->assertOk();

        $this->assertArrayNotHasKey('score', $response->json('data.0'));
        $this->assertArrayNotHasKey('knockouts', $response->json('data.0'));
    }

    public function test_driver_cannot_reach_carrier_endpoints()
    {
        Sanctum::actingAs($this->driverUser());

        $this->getJson('/api/carrier/drivers')->assertStatus(403);
        $this->getJson('/api/carrier/jobs')->assertStatus(403);
    }

    public function test_talent_pool_requires_an_active_subscription()
    {
        Sanctum::actingAs($this->carrier(false));

        $this->getJson('/api/carrier/drivers')
            ->assertStatus(402)
            ->assertJson(['code' => 'subscription_required']);
    }

    public function test_talent_pool_ranks_by_score_and_pushes_knockouts_last()
    {
        Sanctum::actingAs($this->carrier());

        DriverProfile::create([
            'first_name' => 'Weak', 'last_name' => 'Driver', 'cdl_class' => 'A',
            'cdl_expires_at' => now()->addYear(), 'years_experience' => 2,
            'accidents_3y' => 2, 'moving_violations_3y' => 3, 'jobs_last_3_years' => 5,
        ]);

        DriverProfile::create([
            'first_name' => 'Strong', 'last_name' => 'Driver', 'cdl_class' => 'A',
            'cdl_expires_at' => now()->addYears(4), 'years_experience' => 10,
            'accidents_3y' => 0, 'moving_violations_3y' => 0, 'jobs_last_3_years' => 1,
            'longest_tenure_months' => 60, 'work_authorization' => 'us_citizen',
        ]);

        DriverProfile::create([
            'first_name' => 'Knocked', 'last_name' => 'Driver', 'cdl_class' => 'A',
            'cdl_expires_at' => now()->addYears(4), 'years_experience' => 12,
            'can_pass_drug_test' => false,
        ]);

        $response = $this->getJson('/api/carrier/drivers?sort=score')->assertOk();

        $this->assertSame(
            ['Strong', 'Weak', 'Knocked'],
            array_column(array_column($response->json('data'), 'driver'), 'first_name')
        );
        $this->assertTrue($response->json('data.2.disqualified'));
    }

    public function test_talent_pool_filters_narrow_the_result()
    {
        Sanctum::actingAs($this->carrier());

        DriverProfile::create([
            'first_name' => 'Texas', 'last_name' => 'Driver', 'state' => 'TX',
            'cdl_class' => 'A', 'cdl_expires_at' => now()->addYear(), 'years_experience' => 4,
        ]);

        DriverProfile::create([
            'first_name' => 'Illinois', 'last_name' => 'Driver', 'state' => 'IL',
            'cdl_class' => 'A', 'cdl_expires_at' => now()->addYear(), 'years_experience' => 4,
        ]);

        $response = $this->getJson('/api/carrier/drivers?state=TX')->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Texas', $response->json('data.0.driver.first_name'));
    }

    public function test_carrier_cannot_see_applicants_of_another_carrier()
    {
        $other = User::create([
            'name' => 'Other', 'email' => 'other@test.com', 'password' => Hash::make('password'),
            'role' => User::ROLE_CARRIER,
        ]);
        $otherCarrier = Carrier::create(['user_id' => $other->id, 'company_name' => 'Other Co']);
        $otherCarrier->forceFill(['fmcsa_verified_at' => now(), 'allowed_to_operate' => true])->save();
        $job = JobPost::create(['carrier_id' => $otherCarrier->id, 'title' => 'Not yours']);

        Sanctum::actingAs($this->carrier());

        $this->getJson("/api/carrier/jobs/{$job->id}/applicants")->assertStatus(403);
    }

    public function test_carrier_can_save_scoring_weight_overrides()
    {
        Sanctum::actingAs($user = $this->carrier());

        $this->putJson('/api/carrier/scoring/overrides', [
            'criteria' => [['key' => 'years_experience', 'weight' => 50]],
        ])->assertOk();

        $this->assertSame(
            50,
            collect($user->carrier->fresh()->scoring_overrides['criteria'])
                ->firstWhere('key', 'years_experience')['weight']
        );
    }

    public function test_recruiter_can_add_a_driver_manually()
    {
        Sanctum::actingAs($this->carrier());

        $this->postJson('/api/carrier/drivers', [
            'first_name'       => 'Qo\'lda',
            'last_name'        => 'Kiritilgan',
            'cdl_class'        => 'A',
            'years_experience' => 7,
        ])->assertCreated()->assertJsonPath('driver.source', 'manual');

        $this->assertDatabaseHas('driver_profiles', ['first_name' => 'Qo\'lda', 'source' => 'manual']);
    }

    public function test_verification_code_verifies_the_account()
    {
        $user = User::create([
            'name' => 'Yangi', 'email' => 'yangi@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);

        Sanctum::actingAs($user);

        $code = $this->postJson('/api/auth/send-code', ['channel' => 'email'])
            ->assertOk()
            ->json('verification.debug_code');

        $this->postJson('/api/auth/verify-code', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.is_verified', true);
    }

    public function test_a_wrong_verification_code_is_rejected()
    {
        $user = User::create([
            'name' => 'Yangi', 'email' => 'yangi@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/auth/send-code', ['channel' => 'email']);

        $this->postJson('/api/auth/verify-code', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->is_verified);
    }
}
