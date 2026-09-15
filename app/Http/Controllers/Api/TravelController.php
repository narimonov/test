<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\TravelBooking;
use App\Services\TravelService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Flights for a hired driver heading to orientation.
 */
class TravelController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'bookings' => TravelBooking::with('driverProfile:id,first_name,last_name')
                ->where('carrier_id', $request->user()->carrier->id)
                ->latest()
                ->get(),
        ]);
    }

    public function search(Request $request, TravelService $travel)
    {
        $data = $request->validate([
            'origin'      => ['required', 'string', 'size:3'],
            'destination' => ['required', 'string', 'size:3'],
            'depart_on'   => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $offers = $travel->search($data['origin'], $data['destination'], $data['depart_on']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['offers' => $offers]);
    }

    public function book(Request $request, DriverProfile $driverProfile, TravelService $travel)
    {
        $data = $request->validate([
            'offer_id'      => ['required', 'string'],
            'origin'        => ['required', 'string', 'size:3'],
            'destination'   => ['required', 'string', 'size:3'],
            'depart_on'     => ['required', 'date'],
            'airline'       => ['nullable', 'string', 'max:100'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'departs_at'    => ['nullable', 'date'],
            'arrives_at'    => ['nullable', 'date'],
            'amount_cents'  => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $booking = $travel->book($request->user()->carrier, $driverProfile, $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'booking' => $booking,
            'message' => 'Flight booked. The travel step of onboarding is now done.',
        ], 201);
    }

    /** Record a flight the carrier booked somewhere else. */
    public function record(Request $request, DriverProfile $driverProfile, TravelService $travel)
    {
        $data = $request->validate([
            'booking_reference' => ['nullable', 'string', 'max:20'],
            'origin'            => ['nullable', 'string', 'size:3'],
            'destination'       => ['nullable', 'string', 'size:3'],
            'depart_on'         => ['nullable', 'date'],
            'airline'           => ['nullable', 'string', 'max:100'],
            'flight_number'     => ['nullable', 'string', 'max:20'],
            'amount_cents'      => ['nullable', 'integer', 'min:0'],
            'note'              => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json([
            'booking' => $travel->record($request->user()->carrier, $driverProfile, $data),
            'message' => 'Recorded. The travel step of onboarding is now done.',
        ], 201);
    }
}
