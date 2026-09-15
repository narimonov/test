<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\DriverScoringService;
use Illuminate\Http\Request;

class ScoringController extends Controller
{
    /**
     * UI kriteriyalar va og'irliklarni shu yerdan oladi — frontendda
     * kriteriyalar qattiq yozilmagan, hammasi config'dan keladi.
     */
    public function index(Request $request, DriverScoringService $scoring)
    {
        $overrides = optional($request->user())->isCarrier()
            ? optional(Carrier::where('user_id', $request->user()->id)->first())->scoring_overrides
            : null;

        return response()->json($scoring->withOverrides($overrides)->criteriaSummary());
    }

    /** Carrier o'z og'irliklarini sozlaydi (weight/knockout override). */
    public function updateOverrides(Request $request, DriverScoringService $scoring)
    {
        $data = $request->validate([
            'knockouts'   => ['nullable', 'array'],
            'criteria'    => ['nullable', 'array'],
            'criteria.*.key'    => ['required_with:criteria', 'string'],
            'criteria.*.weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tiers'       => ['nullable', 'array'],
        ]);

        $carrier = Carrier::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['company_name' => $request->user()->name]
        );

        $carrier->scoring_overrides = $data ?: null;
        $carrier->save();

        return response()->json([
            'message'  => 'Kriteriyalar saqlandi.',
            'criteria' => $scoring->withOverrides($carrier->scoring_overrides)->criteriaSummary(),
        ]);
    }
}
