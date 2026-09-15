<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverOnboarding;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\MvrReport;
use App\Models\MvrStateRate;
use App\Models\TravelBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvrAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function carrier(string $email = 'boss@test.com'): User
    {
        $user = User::create([
            'name' => 'Boss', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_CARRIER,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Carrier::create(['user_id' => $user->id, 'company_name' => 'Test Carrier', 'dot_number' => '3312445'])
            ->forceFill([
                'fmcsa_verified_at'       => now(),
                'allowed_to_operate'      => true,
                'subscription_plan'       => 'growth',
                'subscription_status'     => 'active',
                'subscription_expires_at' => now()->addMonth(),
            ])->save();

        return $user->fresh();
    }

    /** A driver who has authorised an MVR check and given a licence number. */
    protected function driver(bool $consented = true, string $email = 'jasur@test.com'): User
    {
        $user = User::create([
            'name' => 'Jasur Driver', 'email' => $email,
            'password' => Hash::make('password'), 'role' => User::ROLE_DRIVER,
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'mvr_consent_at'    => $consented ? now() : null,
        ])->save();

        DriverProfile::create([
            'user_id' => $user->id, 'first_name' => 'Jasur', 'last_name' => 'Driver',
            'cdl_class' => 'A', 'cdl_state' => 'IL', 'cdl_number' => 'T52188349901',
            'cdl_expires_at' => now()->addYears(3), 'years_experience' => 6,
            'driver_type' => 'company_driver', 'accidents_3y' => 1, 'moving_violations_3y' => 2,
        ]);

        return $user->fresh();
    }

    protected function hire(User $carrierUser, DriverProfile $driver): Application
    {
        $job = JobPost::create(['carrier_id' => $carrierUser->carrier->id, 'title' => 'OTR']);
        $application = Application::create([
            'job_post_id' => $job->id, 'driver_profile_id' => $driver->id,
        ]);

        Sanctum::actingAs($carrierUser);
        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])->assertOk();

        return $application->fresh();
    }

    // ------------------------------------------------------------------
    // MVR
    // ------------------------------------------------------------------

    public function test_an_mvr_cannot_be_ordered_without_the_drivers_authorisation()
    {
        $driver = $this->driver(false)->driverProfile;

        Sanctum::actingAs($this->carrier());

        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_profile_id');

        $this->assertSame(0, MvrReport::count());
    }

    public function test_an_mvr_cannot_be_ordered_without_a_licence_number()
    {
        $driverUser = $this->driver();
        $driverUser->driverProfile->forceFill(['cdl_number' => null])->save();

        Sanctum::actingAs($this->carrier());

        $this->postJson("/api/carrier/drivers/{$driverUser->driverProfile->id}/mvr")
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_profile_id');
    }

    public function test_ordering_an_mvr_stores_the_findings_and_the_cost()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());

        $response = $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        $report = MvrReport::first();

        $this->assertSame('ordered', $response->json('source'));
        $this->assertSame(MvrReport::STATUS_COMPLETED, $report->status);
        $this->assertSame('IL', $report->state);
        $this->assertSame(2, $report->violations_count);
        $this->assertSame(1, $report->accidents_count);
        $this->assertSame('9901', $report->licence_number_last4);
        $this->assertGreaterThan(0, $report->cost_cents);
    }

    public function test_a_recent_record_is_reused_instead_of_bought_again()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());
        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        // A second carrier asks for the same driver.
        Sanctum::actingAs($this->carrier('second@test.com'));

        $response = $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        $this->assertSame('reused', $response->json('source'));
        $this->assertSame(0, $response->json('cost_cents'));
        $this->assertSame(1, MvrReport::count());
        $this->assertSame(2, MvrReport::first()->shares()->count());
    }

    public function test_a_record_older_than_the_reuse_window_is_ordered_again()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());
        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        MvrReport::first()->forceFill([
            'completed_at' => now()->subDays(config('mvr.reuse_window_days') + 1),
        ])->save();

        $this->assertSame('ordered', $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->json('source'));
        $this->assertSame(2, MvrReport::count());
    }

    public function test_the_quote_says_whether_a_record_can_be_reused()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());

        $before = $this->getJson("/api/carrier/drivers/{$driver->id}/mvr/quote")->assertOk();
        $this->assertFalse($before->json('reusable'));
        $this->assertGreaterThan(0, $before->json('cost_cents'));

        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        $after = $this->getJson("/api/carrier/drivers/{$driver->id}/mvr/quote")->assertOk();
        $this->assertTrue($after->json('reusable'));
        $this->assertSame(0, $after->json('cost_cents'));
    }

    public function test_state_pricing_comes_from_the_synced_rates_first()
    {
        MvrStateRate::create([
            'provider' => 'fake', 'state' => 'IL', 'cost_cents' => 4321, 'synced_at' => now(),
        ]);

        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());

        $this->assertSame(
            4321,
            $this->getJson("/api/carrier/drivers/{$driver->id}/mvr/quote")->json('cost_cents')
        );
    }

    public function test_a_carrier_cannot_read_a_record_it_was_never_given()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());
        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        Sanctum::actingAs($this->carrier('outsider@test.com'));

        $this->assertCount(0, $this->getJson("/api/carrier/drivers/{$driver->id}/mvr")->json('reports'));
        $this->postJson('/api/carrier/mvr/' . MvrReport::first()->id . '/refresh')->assertStatus(403);
    }

    public function test_the_licence_number_never_leaves_the_server()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());

        $body = $this->getJson('/api/carrier/drivers?q=Jasur')->assertOk()->getContent();

        $this->assertStringNotContainsString('T52188349901', $body);
        $this->assertStringContainsString('9901', $body);   // last four only
    }

    // ------------------------------------------------------------------
    // Onboarding
    // ------------------------------------------------------------------

    public function test_hiring_starts_onboarding_with_the_right_track()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;

        $this->hire($carrierUser, $driver);

        $onboarding = DriverOnboarding::first();

        $this->assertNotNull($onboarding);
        $this->assertSame('company_driver', $onboarding->track);
        $this->assertSame(
            count(config('onboarding.tracks.company_driver.steps')),
            $onboarding->steps()->count()
        );
    }

    public function test_an_owner_operator_gets_the_owner_operator_track()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();
        $driverUser->driverProfile->forceFill(['driver_type' => 'owner_operator'])->save();

        $this->hire($carrierUser, $driverUser->driverProfile->fresh());

        $onboarding = DriverOnboarding::first();

        $this->assertSame('owner_operator', $onboarding->track);
        $this->assertNotNull($onboarding->steps()->where('key', 'lease_agreement')->first());
    }

    public function test_hiring_twice_does_not_duplicate_the_checklist()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $application = $this->hire($carrierUser, $driver);

        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'screening'])->assertOk();
        $this->putJson("/api/carrier/applications/{$application->id}/status", ['status' => 'hired'])->assertOk();

        $this->assertSame(1, DriverOnboarding::count());
    }

    public function test_progress_counts_required_steps_only()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $this->hire($carrierUser, $driver);

        $onboarding = DriverOnboarding::first();
        $optional = $onboarding->steps()->where('is_required', false)->first();

        Sanctum::actingAs($carrierUser);

        $response = $this->putJson("/api/onboarding/{$onboarding->id}/steps/{$optional->id}", ['status' => 'done'])
            ->assertOk();

        // An optional step moves nothing.
        $this->assertSame(0, $response->json('onboarding.progress.done'));
    }

    public function test_a_driver_can_only_move_their_own_steps()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();
        $this->hire($carrierUser, $driverUser->driverProfile);

        $onboarding = DriverOnboarding::first();
        $carrierStep = $onboarding->steps()->where('owner', 'carrier')->first();
        $driverStep = $onboarding->steps()->where('owner', 'driver')->first();

        Sanctum::actingAs($driverUser);

        $this->putJson("/api/onboarding/{$onboarding->id}/steps/{$carrierStep->id}", ['status' => 'done'])
            ->assertStatus(403);

        $this->putJson("/api/onboarding/{$onboarding->id}/steps/{$driverStep->id}", ['status' => 'done'])
            ->assertOk();
    }

    public function test_someone_elses_onboarding_is_not_readable()
    {
        $carrierUser = $this->carrier();
        $this->hire($carrierUser, $this->driver()->driverProfile);

        Sanctum::actingAs($this->carrier('outsider@test.com'));

        $this->getJson('/api/onboarding/' . DriverOnboarding::first()->id)->assertStatus(403);
    }

    public function test_ordering_an_mvr_closes_the_mvr_step()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $this->hire($carrierUser, $driver);

        $this->postJson("/api/carrier/drivers/{$driver->id}/mvr")->assertCreated();

        $step = DriverOnboarding::first()->steps()->where('key', 'mvr')->first();

        $this->assertSame('done', $step->status);
    }

    public function test_onboarding_completes_once_every_required_step_is_done()
    {
        $carrierUser = $this->carrier();
        $driverUser = $this->driver();
        $this->hire($carrierUser, $driverUser->driverProfile);

        $onboarding = DriverOnboarding::first();

        foreach ($onboarding->steps()->where('is_required', true)->get() as $step) {
            Sanctum::actingAs($step->owner === 'driver' ? $driverUser : $carrierUser);
            $this->putJson("/api/onboarding/{$onboarding->id}/steps/{$step->id}", ['status' => 'done'])->assertOk();
        }

        $this->assertSame('completed', $onboarding->fresh()->status);
        $this->assertNotNull($onboarding->fresh()->completed_at);
    }

    // ------------------------------------------------------------------
    // Travel
    // ------------------------------------------------------------------

    public function test_flights_can_be_searched_and_booked_for_a_hired_driver()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $this->hire($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);

        $offers = $this->postJson('/api/carrier/travel/search', [
            'origin' => 'MDW', 'destination' => 'DFW', 'depart_on' => today()->addWeek()->toDateString(),
        ])->assertOk()->json('offers');

        $this->assertNotEmpty($offers);

        $offer = $offers[0];

        $this->postJson("/api/carrier/drivers/{$driver->id}/travel", [
            'offer_id'     => $offer['id'],
            'origin'       => $offer['origin'],
            'destination'  => $offer['destination'],
            'depart_on'    => substr($offer['departs_at'], 0, 10),
            'airline'      => $offer['airline'],
            'flight_number' => $offer['flight_number'],
            'amount_cents' => $offer['amount_cents'],
        ])->assertCreated();

        $booking = TravelBooking::first();

        $this->assertSame('booked', $booking->status);
        $this->assertNotNull($booking->booking_reference);

        // Booking satisfies the travel step.
        $this->assertSame('done', DriverOnboarding::first()->steps()->where('key', 'travel')->first()->status);
    }

    public function test_travel_cannot_be_arranged_for_a_driver_you_have_not_hired()
    {
        $driver = $this->driver()->driverProfile;

        Sanctum::actingAs($this->carrier());

        $this->postJson("/api/carrier/drivers/{$driver->id}/travel", [
            'offer_id' => 'x', 'origin' => 'MDW', 'destination' => 'DFW',
            'depart_on' => today()->addWeek()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('driver_profile_id');
    }

    public function test_a_flight_booked_elsewhere_can_be_recorded()
    {
        $carrierUser = $this->carrier();
        $driver = $this->driver()->driverProfile;
        $this->hire($carrierUser, $driver);

        Sanctum::actingAs($carrierUser);

        $this->postJson("/api/carrier/drivers/{$driver->id}/travel/record", [
            'booking_reference' => 'ABC123',
            'airline'           => 'Delta',
            'flight_number'     => 'DL404',
            'depart_on'         => today()->addWeek()->toDateString(),
        ])->assertCreated();

        $this->assertSame('recorded', TravelBooking::first()->status);
        $this->assertSame('done', DriverOnboarding::first()->steps()->where('key', 'travel')->first()->status);
    }

    // ------------------------------------------------------------------
    // Carrier reputation
    // ------------------------------------------------------------------

    public function test_a_driver_can_look_up_what_is_said_about_a_carrier()
    {
        $carrierUser = $this->carrier();

        Sanctum::actingAs($this->driver());

        $response = $this->getJson("/api/carriers/{$carrierUser->carrier->id}/reputation")->assertOk();

        $sources = collect($response->json('sources'))->pluck('source');

        $this->assertTrue($sources->contains('fmcsa_safety'));
        $this->assertNotNull($response->json('platform'));
    }

    public function test_a_snapshot_is_not_refetched_while_it_is_still_fresh()
    {
        $carrierUser = $this->carrier();

        Sanctum::actingAs($this->driver());

        $this->getJson("/api/carriers/{$carrierUser->carrier->id}/reputation")->assertOk();
        $first = \App\Models\CarrierReputationSnapshot::count();

        $this->getJson("/api/carriers/{$carrierUser->carrier->id}/reputation")->assertOk();

        $this->assertSame($first, \App\Models\CarrierReputationSnapshot::count());
    }

    public function test_only_the_carrier_itself_or_an_admin_can_force_a_refresh()
    {
        $carrierUser = $this->carrier();

        Sanctum::actingAs($this->driver());
        $this->postJson("/api/carriers/{$carrierUser->carrier->id}/reputation/refresh")->assertStatus(403);

        Sanctum::actingAs($carrierUser);
        $this->postJson("/api/carriers/{$carrierUser->carrier->id}/reputation/refresh")->assertOk();
    }
}
