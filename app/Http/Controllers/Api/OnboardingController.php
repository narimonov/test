<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverOnboarding;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The checklist a hired driver works through. Carriers see everyone they have
 * hired; a driver sees only their own.
 */
class OnboardingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DriverOnboarding::with(['steps', 'driverProfile:id,first_name,last_name,phone', 'carrier:id,company_name']);

        if ($user->isCarrier()) {
            $query->where('carrier_id', $user->carrier->id);
        } else {
            $query->where('driver_profile_id', optional($user->driverProfile)->id);
        }

        return response()->json([
            'onboardings' => $query->latest()->get(),
            'stages'      => config('onboarding.stages'),
        ]);
    }

    public function show(Request $request, DriverOnboarding $onboarding)
    {
        $this->authorizeAccess($request, $onboarding);

        return response()->json([
            'onboarding' => $onboarding->load(['steps', 'driverProfile', 'carrier:id,company_name']),
            'stages'     => config('onboarding.stages'),
        ]);
    }

    public function updateStep(Request $request, DriverOnboarding $onboarding, int $step, OnboardingService $service)
    {
        $this->authorizeAccess($request, $onboarding);

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'done', 'skipped', 'failed'])],
            'note'   => ['nullable', 'string', 'max:1000'],
        ]);

        $target = $onboarding->steps()->findOrFail($step);

        // A driver can only move their own steps; the rest belong to the carrier.
        if ($request->user()->isDriver() && $target->owner !== 'driver') {
            return response()->json([
                'message' => 'Your carrier handles this step.',
            ], 403);
        }

        return response()->json([
            'onboarding' => $service->updateStep($onboarding, $step, $data['status'], $request->user(), $data['note'] ?? null),
        ]);
    }

    protected function authorizeAccess(Request $request, DriverOnboarding $onboarding): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        $allowed = $user->isCarrier()
            ? $onboarding->carrier_id === optional($user->carrier)->id
            : $onboarding->driver_profile_id === optional($user->driverProfile)->id;

        abort_unless($allowed, 403, 'This onboarding is not yours.');
    }
}
