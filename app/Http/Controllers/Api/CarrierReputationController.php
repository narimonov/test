<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Services\CarrierReputationService;
use Illuminate\Http\Request;

/**
 * What the internet says about a carrier, alongside its reviews here.
 * Readable by any signed-in user — drivers should see it before applying.
 */
class CarrierReputationController extends Controller
{
    public function show(Request $request, Carrier $carrier, CarrierReputationService $reputation)
    {
        return response()->json([
            'carrier' => [
                'id'           => $carrier->id,
                'company_name' => $carrier->company_name,
                'dot_number'   => $carrier->dot_number,
                'city'         => $carrier->city,
                'state'        => $carrier->state,
            ],
        ] + $reputation->refresh($carrier));
    }

    /** Force a re-fetch; carriers may do this for themselves, admins for anyone. */
    public function refresh(Request $request, Carrier $carrier, CarrierReputationService $reputation)
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $carrier->id === optional($user->carrier)->id,
            403
        );

        return response()->json($reputation->refresh($carrier, true));
    }
}
