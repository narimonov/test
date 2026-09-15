<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\BlacklistAppeal;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReputationAndHiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['documents.disk' => 'local']);
    }

    /**
     * Submit a review with the proof the platform now requires.
     */
    protected function leaveReview(Application $application, int $rating, array $extra = [])
    {
        return $this->post('/api/reviews', array_merge([
            'application_id' => $application->id,
            'rating'         => $rating,
            'proofs'         => [UploadedFile::fake()->create('rate-con.pdf', 40, 'application/pdf')],
            'proof_kinds'    => ['rate_confirmation'],
        ], $extra), ['Accept' => 'application/json']);
    }

    /**
     * Push a submitted review all the way through moderation, which is the
     * only path that makes it count towards a blacklist.
     */
    protected function publishReview(Review $review): void
    {
        $admin = $this->admin();
        $current = auth('sanctum')->user();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/reviews/{$review->id}/contacted")->assertOk();
        $this->postJson("/api/admin/reviews/{$review->id}/decision", ['decision' => 'publish'])->assertOk();

        if ($current) {
            Sanctum::actingAs($current);
        }
    }

    protected function carrier(string $email = 'boss@test.com'): User
    {
        $user = User::create([
            'name' => 'Boss', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_CARRIER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Carrier::create(['user_id' => $user->id, 'company_name' => 'Carrier ' . $email])
            ->forceFill([
                'fmcsa_verified_at'       => now(),
                'allowed_to_operate'      => true,
                'subscription_plan'       => 'pro',
                'subscription_status'     => 'active',
                'subscription_expires_at' => now()->addMonth(),
            ])->save();

        return $user->fresh();
    }

    protected function driver(): User
    {
        $user = User::create([
            'name' => 'Jasur Driver', 'email' => 'jasur@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        DriverProfile::create([
            'user_id' => $user->id, 'first_name' => 'Jasur', 'last_name' => 'Driver',
            'cdl_class' => 'A', 'cdl_expires_at' => now()->addYears(3), 'years_experience' => 5,
        ]);

        return $user->fresh();
    }

    protected function application(User $carrierUser, DriverProfile $driver, string $status = 'applied'): Application
    {
        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR ' . uniqid()]);

        return Application::create([
            'job_post_id'       => $job->id,
            'driver_profile_id' => $driver->id,
            'status'            => $status,
        ]);
    }

    // ------------------------------------------------------------------
    // Eksklyuzivlik
    // ------------------------------------------------------------------

    public function test_hiring_locks_the_driver_to_that_carrier()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $application = $this->application($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);

        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])
            ->assertOk();

        $driver->refresh();

        $this->assertTrue($driver->is_hired);
        $this->assertSame($carrierUser->carrier->id, $driver->hired_carrier_id);
    }

    public function test_a_second_carrier_cannot_hire_an_already_hired_driver()
    {
        $first = $this->carrier('first@test.com');
        $second = $this->carrier('second@test.com');
        $driver = $this->driver()->driverProfile;

        $firstApplication = $this->application($first, $driver);
        $secondApplication = $this->application($second, $driver);

        Sanctum::actingAs($first);
        $this->putJson("/api/carrier/applications/{$firstApplication->id}/status", ['status' => 'hired'])->assertOk();

        Sanctum::actingAs($second);
        $this->putJson("/api/carrier/applications/{$secondApplication->id}/status", ['status' => 'hired'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_hiring_closes_the_drivers_other_open_applications()
    {
        $first = $this->carrier('first@test.com');
        $second = $this->carrier('second@test.com');
        $driver = $this->driver()->driverProfile;

        $hired = $this->application($first, $driver);
        $other = $this->application($second, $driver);

        Sanctum::actingAs($first);
        $this->putJson("/api/carrier/applications/{$hired->id}/status", ['status' => 'hired'])->assertOk();

        $this->assertSame('rejected', $other->fresh()->status);
    }

    public function test_reverting_a_hire_releases_the_driver()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $application = $this->application($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);
        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])->assertOk();
        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'screening'])->assertOk();

        $this->assertFalse($driver->fresh()->is_hired);
    }

    public function test_hired_drivers_are_hidden_from_the_talent_pool_by_default()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $application = $this->application($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);
        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])->assertOk();

        $this->assertSame(0, $this->getJson('/api/carrier/drivers')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/carrier/drivers?include_hired=1')->json('meta.total'));
    }

    // ------------------------------------------------------------------
    // Review va blacklist
    // ------------------------------------------------------------------

    public function test_a_review_cannot_be_left_before_the_application_is_finished()
    {
        $carrierUser = $this->carrier();
        $application = $this->application($carrierUser, $this->driver()->driverProfile, 'screening');

        Sanctum::actingAs($carrierUser);

        $this->leaveReview($application, 1)
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_id');
    }

    public function test_a_review_without_proof_is_rejected()
    {
        $carrierUser = $this->carrier();
        $application = $this->application($carrierUser, $this->driver()->driverProfile, 'hired');

        Sanctum::actingAs($carrierUser);

        $this->postJson('/api/reviews', ['application_id' => $application->id, 'rating' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proofs');
    }

    public function test_a_submitted_review_is_not_published_and_does_not_count_yet()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($carrierUser);

        for ($i = 0; $i < 3; $i++) {
            $this->leaveReview($this->application($carrierUser, $driver, 'rejected'), 1)->assertCreated();
        }

        $this->assertSame(3, Review::where('status', Review::STATUS_PENDING_REVIEW)->count());
        $this->assertSame(0, Review::published()->count());
        $this->assertFalse($driver->fresh()->is_blacklisted);
    }

    public function test_a_review_cannot_be_published_before_the_other_party_is_contacted()
    {
        $carrierUser = $this->carrier();
        $application = $this->application($carrierUser, $this->driver()->driverProfile, 'hired');

        Sanctum::actingAs($carrierUser);
        $this->leaveReview($application, 4)->assertCreated();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/reviews/' . Review::first()->id . '/decision', ['decision' => 'publish'])
            ->assertStatus(422)
            ->assertJson(['code' => 'contact_required']);
    }

    public function test_a_carrier_cannot_review_an_application_that_is_not_theirs()
    {
        $owner = $this->carrier('owner@test.com');
        $stranger = $this->carrier('stranger@test.com');
        $application = $this->application($owner, $this->driver()->driverProfile, 'hired');

        Sanctum::actingAs($stranger);

        $this->leaveReview($application, 5)->assertStatus(403);
    }

    public function test_the_same_relationship_cannot_be_reviewed_twice()
    {
        $carrierUser = $this->carrier();
        $application = $this->application($carrierUser, $this->driver()->driverProfile, 'rejected');

        Sanctum::actingAs($carrierUser);

        $this->leaveReview($application, 4)->assertCreated();
        $this->leaveReview($application, 2)
            ->assertStatus(422)
            ->assertJsonValidationErrors('application_id');
    }

    public function test_three_negative_reviews_blacklist_the_driver()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($carrierUser);

        for ($i = 0; $i < 2; $i++) {
            $id = $this->leaveReview($this->application($carrierUser, $driver, 'rejected'), 1)
                ->assertCreated()->json('review.id');

            $this->publishReview(Review::find($id));
        }

        $this->assertFalse($driver->fresh()->is_blacklisted);

        $third = $this->leaveReview($this->application($carrierUser, $driver, 'rejected'), 2)
            ->assertCreated()->json('review.id');

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/reviews/' . $third . '/contacted')->assertOk();
        $this->postJson('/api/admin/reviews/' . $third . '/decision', ['decision' => 'publish'])
            ->assertOk()
            ->assertJsonPath('blacklisted', true);

        $this->assertTrue($driver->fresh()->is_blacklisted);
    }

    public function test_good_reviews_do_not_blacklist()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($carrierUser);

        for ($i = 0; $i < 4; $i++) {
            $id = $this->leaveReview($this->application($carrierUser, $driver, 'hired'), 3)
                ->assertCreated()->json('review.id');

            $this->publishReview(Review::find($id));
        }

        $this->assertFalse($driver->fresh()->is_blacklisted);
    }

    public function test_the_rule_applies_to_carriers_too()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();

        Sanctum::actingAs($driverUser);

        for ($i = 0; $i < 3; $i++) {
            $id = $this->leaveReview($this->application($carrierUser, $driverUser->driverProfile, 'rejected'), 1)
                ->assertCreated()->json('review.id');

            $this->publishReview(Review::find($id));
            Sanctum::actingAs($driverUser);
        }

        $this->assertTrue($carrierUser->carrier->fresh()->is_blacklisted);
    }

    public function test_a_blacklisted_driver_cannot_apply()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();
        $driverUser->driverProfile->forceFill(['blacklisted_at' => now(), 'blacklist_reason' => 'Test'])->save();

        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR']);

        Sanctum::actingAs($driverUser);

        $this->postJson("/api/driver/jobs/{$job->id}/apply")
            ->assertStatus(403)
            ->assertJson(['code' => 'driver_blacklisted']);
    }

    public function test_a_blacklisted_driver_cannot_be_hired()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $driver->forceFill(['blacklisted_at' => now()])->save();

        $application = $this->application($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);

        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Apelyatsiya
    // ------------------------------------------------------------------

    public function test_an_appeal_needs_an_actual_blacklist()
    {
        Sanctum::actingAs($this->driver());

        $this->postJson('/api/appeals', ['reason' => str_repeat('a', 30)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_only_one_appeal_can_be_pending()
    {
        $driverUser = $this->driver();
        $driverUser->driverProfile->forceFill(['blacklisted_at' => now()])->save();

        Sanctum::actingAs($driverUser);

        $this->postJson('/api/appeals', ['reason' => str_repeat('a', 30)])->assertCreated();
        $this->postJson('/api/appeals', ['reason' => str_repeat('b', 30)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_an_approved_appeal_lifts_the_blacklist_without_re_triggering_it()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();
        $driver = $driverUser->driverProfile;

        // Three published unsatisfactory reviews -> blacklist
        Sanctum::actingAs($carrierUser);
        for ($i = 0; $i < 3; $i++) {
            $id = $this->leaveReview($this->application($carrierUser, $driver, 'rejected'), 1)
                ->assertCreated()->json('review.id');

            $this->publishReview(Review::find($id));
            Sanctum::actingAs($carrierUser);
        }
        $this->assertTrue($driver->fresh()->is_blacklisted);

        // The blacklist was set on another instance; reload the user, as a
        // real request would.
        Sanctum::actingAs($driverUser->fresh());
        $this->postJson('/api/appeals', ['reason' => str_repeat('a', 30)])->assertCreated();

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/appeals/' . BlacklistAppeal::first()->id . '/decision', [
            'decision' => 'approved',
            'note'     => 'Asossiz',
        ])->assertOk();

        $driver->refresh();

        $this->assertFalse($driver->is_blacklisted);

        // The old reviews survive but no longer count.
        $this->assertSame(3, Review::published()->where('driver_profile_id', $driver->id)->count());

        Sanctum::actingAs($carrierUser);
        $newId = $this->leaveReview($this->application($carrierUser, $driver, 'rejected'), 1)
            ->assertCreated()->json('review.id');

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/admin/reviews/{$newId}/contacted")->assertOk();
        $this->postJson("/api/admin/reviews/{$newId}/decision", ['decision' => 'publish'])
            ->assertOk()
            ->assertJsonPath('blacklisted', false);
    }

    public function test_a_decided_appeal_cannot_be_decided_again()
    {
        $driverUser = $this->driver();
        $driverUser->driverProfile->forceFill(['blacklisted_at' => now()])->save();

        Sanctum::actingAs($driverUser);
        $this->postJson('/api/appeals', ['reason' => str_repeat('a', 30)])->assertCreated();

        Sanctum::actingAs($this->admin());
        $appealId = BlacklistAppeal::first()->id;

        $this->postJson("/api/admin/appeals/{$appealId}/decision", ['decision' => 'rejected'])->assertOk();
        $this->postJson("/api/admin/appeals/{$appealId}/decision", ['decision' => 'approved'])->assertStatus(422);
    }

    public function test_non_admins_cannot_reach_admin_endpoints()
    {
        Sanctum::actingAs($this->driver());
        $this->getJson('/api/admin/overview')->assertStatus(403);

        Sanctum::actingAs($this->carrier());
        $this->getJson('/api/admin/users')->assertStatus(403);
    }

    protected function admin(): User
    {
        if ($existing = User::where('email', 'admin@test.com')->first()) {
            return $existing;
        }

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
