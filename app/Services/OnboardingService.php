<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Carrier;
use App\Models\DriverOnboarding;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds and advances a hired driver's onboarding.
 *
 * Each driver gets their own copy of the step list, so editing the template in
 * config later does not rewrite anybody's record.
 */
class OnboardingService
{
    /** Called when a driver is marked hired. Safe to call twice. */
    public function start(DriverProfile $driver, Carrier $carrier, Application $application = null): DriverOnboarding
    {
        $existing = DriverOnboarding::where('driver_profile_id', $driver->id)
            ->where('carrier_id', $carrier->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $track = $this->trackFor($driver);

        return DB::transaction(function () use ($driver, $carrier, $application, $track) {
            $onboarding = DriverOnboarding::create([
                'driver_profile_id' => $driver->id,
                'carrier_id'        => $carrier->id,
                'application_id'    => optional($application)->id,
                'track'             => $track,
                'start_date'        => today(),
            ]);

            foreach (config("onboarding.tracks.{$track}.steps", []) as $position => $step) {
                $onboarding->steps()->create([
                    'key'         => $step['key'],
                    'label'       => $step['label'],
                    'stage'       => $step['stage'],
                    'description' => $step['description'] ?? null,
                    'owner'       => $step['owner'] ?? 'carrier',
                    'is_required' => $step['required'] ?? true,
                    'position'    => $position,
                ]);
            }

            return $onboarding->fresh('steps');
        });
    }

    public function updateStep(DriverOnboarding $onboarding, int $stepId, string $status, User $user, string $note = null): DriverOnboarding
    {
        $step = $onboarding->steps()->findOrFail($stepId);

        $step->forceFill([
            'status'               => $status,
            'note'                 => $note,
            'completed_by_user_id' => in_array($status, ['done', 'skipped'], true) ? $user->id : null,
            'completed_at'         => in_array($status, ['done', 'skipped'], true) ? now() : null,
        ])->save();

        return $this->refreshStatus($onboarding);
    }

    /**
     * Marks a step done because something else in the platform satisfied it —
     * an MVR that came back, a flight that was booked. Never downgrades a step
     * a person already worked.
     */
    public function completeByKey(DriverOnboarding $onboarding, string $key, string $note = null): void
    {
        $step = $onboarding->steps()->where('key', $key)->first();

        if (! $step || in_array($step->status, ['done', 'skipped'], true)) {
            return;
        }

        $step->forceFill([
            'status'       => 'done',
            'note'         => $note,
            'completed_at' => now(),
        ])->save();

        $this->refreshStatus($onboarding);
    }

    protected function refreshStatus(DriverOnboarding $onboarding): DriverOnboarding
    {
        $onboarding->load('steps');
        $progress = $onboarding->progress;

        $onboarding->forceFill([
            'status'       => $progress['done'] >= $progress['required'] ? 'completed' : 'in_progress',
            'completed_at' => $progress['done'] >= $progress['required'] ? now() : null,
        ])->save();

        return $onboarding->fresh('steps');
    }

    protected function trackFor(DriverProfile $driver): string
    {
        $track = $driver->driver_type === 'company_driver' ? 'company_driver' : 'owner_operator';

        return config("onboarding.tracks.{$track}") ? $track : 'company_driver';
    }
}
