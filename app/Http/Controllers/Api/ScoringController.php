<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\DriverScoringService;
use App\Services\PlanGate;
use Illuminate\Http\Request;

class ScoringController extends Controller
{
    /**
     * The UI reads criteria and weights from here; nothing about the criteria is
     * hard-coded in the frontend.
     */
    public function index(Request $request, DriverScoringService $scoring)
    {
        $overrides = optional($request->user())->isCarrier()
            ? optional(Carrier::where('user_id', $request->user()->id)->first())->scoring_overrides
            : null;

        return response()->json($scoring->withOverrides($overrides)->criteriaSummary());
    }

    /** A carrier tunes its own weights and knockout overrides. */
    public function updateOverrides(Request $request, DriverScoringService $scoring, PlanGate $plans)
    {
        $carrier = Carrier::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['company_name' => $request->user()->name]
        );

        if (! $plans->allows($carrier, 'criteria_tuning')) {
            return response()->json([
                'message' => 'Tuning the scoring weights is part of the Growth plan.',
                'code'    => 'upgrade_required',
            ], 402);
        }

        $data = $request->validate([
            'knockouts'   => ['nullable', 'array'],
            'criteria'    => ['nullable', 'array'],
            'criteria.*.key'    => ['required_with:criteria', 'string'],
            'criteria.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tiers'       => ['nullable', 'array'],
        ]);

        $carrier->scoring_overrides = $data ?: null;
        $carrier->save();

        return response()->json([
            'message'  => 'Criteria saved.',
            'criteria' => $scoring->withOverrides($carrier->scoring_overrides)->criteriaSummary(),
        ]);
    }
}
