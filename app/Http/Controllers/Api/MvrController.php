<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\MvrReport;
use App\Services\MvrService;
use App\Services\OnboardingService;
use App\Models\DriverOnboarding;
use Illuminate\Http\Request;

/**
 * Motor vehicle records for a driver a carrier is considering.
 *
 * Ordering requires the driver's recorded authorisation (FCRA and DPPA); the
 * service refuses without it.
 */
class MvrController extends Controller
{
    /** What it would cost and whether an existing record can be reused. */
    public function quote(Request $request, DriverProfile $driverProfile, MvrService $mvr)
    {
        $state = strtoupper((string) $request->query('state', $driverProfile->cdl_state ?: $driverProfile->state));
        $reusable = strlen($state) === 2 ? $mvr->reusableReport($driverProfile, $state) : null;

        return response()->json([
            'state'            => $state ?: null,
            'cost_cents'       => $reusable ? 0 : ($state ? $mvr->costFor($state) : null),
            'reusable'         => (bool) $reusable,
            'reusable_report'  => $reusable,
            'reuse_window_days' => (int) config('mvr.reuse_window_days'),
            'driver_authorised' => $driverProfile->user && $driverProfile->user->mvr_consent_at !== null,
            'has_licence_number' => (bool) $driverProfile->cdl_number,
        ]);
    }

    public function store(Request $request, DriverProfile $driverProfile, MvrService $mvr, OnboardingService $onboarding)
    {
        $data = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
        ]);

        $carrier = $request->user()->carrier;

        $result = $mvr->obtain($driverProfile, $carrier, $data['state'] ?? null);

        // A record on file satisfies the MVR step of onboarding.
        $record = DriverOnboarding::where('driver_profile_id', $driverProfile->id)
            ->where('carrier_id', $carrier->id)
            ->first();

        if ($record && $result['report']->status === MvrReport::STATUS_COMPLETED) {
            $onboarding->completeByKey(
                $record,
                'mvr',
                $result['source'] === 'reused' ? 'Reused a record pulled within the last month' : null
            );
        }

        return response()->json([
            'report'     => $result['report'],
            'source'     => $result['source'],
            'cost_cents' => $result['cost_cents'],
            'message'    => $result['source'] === 'reused'
                ? 'A record pulled in the last ' . config('mvr.reuse_window_days') . ' days was reused — no state fee.'
                : 'Record ordered.',
        ], 201);
    }

    /** Records this carrier has access to for a driver. */
    public function index(Request $request, DriverProfile $driverProfile)
    {
        $carrierId = $request->user()->carrier->id;

        $reports = MvrReport::where('driver_profile_id', $driverProfile->id)
            ->whereHas('shares', fn ($query) => $query->where('carrier_id', $carrierId))
            ->latest('ordered_at')
            ->get();

        return response()->json(['reports' => $reports]);
    }

    /** Collect a record that came back pending. */
    public function refresh(Request $request, MvrReport $mvrReport, MvrService $mvr)
    {
        $carrierId = $request->user()->carrier->id;

        abort_unless(
            $mvrReport->shares()->where('carrier_id', $carrierId)->exists(),
            403,
            'You do not have access to this record.'
        );

        return response()->json(['report' => $mvr->refresh($mvrReport)]);
    }

    /** Per-state pricing, so a carrier sees the cost before ordering. */
    public function rates(MvrService $mvr)
    {
        return response()->json([
            'provider' => config('mvr.driver'),
            'rates'    => \App\Models\MvrStateRate::orderBy('state')->get(),
            'fallback' => config('mvr.fallback_rates'),
            'default_cost_cents' => config('mvr.default_cost_cents'),
        ]);
    }
}
