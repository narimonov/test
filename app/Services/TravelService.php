<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\DriverOnboarding;
use App\Models\DriverProfile;
use App\Models\TravelBooking;
use App\Services\Travel\FlightProvider;
use Illuminate\Validation\ValidationException;

/**
 * Getting a hired driver to orientation.
 *
 * A carrier can book through us, or record a booking it made elsewhere. Either
 * way the travel step of onboarding is satisfied, because what matters is that
 * the driver can get there.
 */
class TravelService
{
    /** @var FlightProvider */
    protected $flights;

    /** @var OnboardingService */
    protected $onboarding;

    public function __construct(FlightProvider $flights, OnboardingService $onboarding)
    {
        $this->flights = $flights;
        $this->onboarding = $onboarding;
    }

    public function search(string $origin, string $destination, string $departOn): array
    {
        return array_map(
            fn ($offer) => $offer->toArray(),
            $this->flights->search($origin, $destination, $departOn)
        );
    }

    public function book(Carrier $carrier, DriverProfile $driver, array $data): TravelBooking
    {
        $this->assertDriverBelongsToCarrier($driver, $carrier);

        $result = $this->flights->book($data['offer_id'], [
            'first_name'    => $driver->first_name,
            'last_name'     => $driver->last_name,
            'date_of_birth' => optional($driver->date_of_birth)->toDateString(),
            'email'         => $driver->email,
            'phone'         => $driver->phone,
        ]);

        $booking = TravelBooking::create([
            'carrier_id'            => $carrier->id,
            'driver_profile_id'     => $driver->id,
            'driver_onboarding_id'  => optional($this->onboardingFor($driver, $carrier))->id,
            'provider'              => $this->flights->name(),
            'provider_reference'    => $result['reference'],
            'booking_reference'     => $result['booking_reference'],
            'origin'                => strtoupper($data['origin']),
            'destination'           => strtoupper($data['destination']),
            'depart_on'             => $data['depart_on'],
            'carrier_name'          => $data['airline'] ?? null,
            'flight_number'         => $data['flight_number'] ?? null,
            'departs_at'            => $data['departs_at'] ?? null,
            'arrives_at'            => $data['arrives_at'] ?? null,
            'amount_cents'          => $data['amount_cents'] ?? null,
            'status'                => $result['status'],
            'payload'               => $result['payload'],
        ]);

        $this->markTravelDone($driver, $carrier, $booking);

        return $booking;
    }

    /** A flight the carrier bought somewhere else. */
    public function record(Carrier $carrier, DriverProfile $driver, array $data): TravelBooking
    {
        $this->assertDriverBelongsToCarrier($driver, $carrier);

        $booking = TravelBooking::create([
            'carrier_id'           => $carrier->id,
            'driver_profile_id'    => $driver->id,
            'driver_onboarding_id' => optional($this->onboardingFor($driver, $carrier))->id,
            'provider'             => 'external',
            'booking_reference'    => $data['booking_reference'] ?? null,
            'origin'               => isset($data['origin']) ? strtoupper($data['origin']) : null,
            'destination'          => isset($data['destination']) ? strtoupper($data['destination']) : null,
            'depart_on'            => $data['depart_on'] ?? null,
            'carrier_name'         => $data['airline'] ?? null,
            'flight_number'        => $data['flight_number'] ?? null,
            'amount_cents'         => $data['amount_cents'] ?? null,
            'status'               => 'recorded',
            'note'                 => $data['note'] ?? null,
        ]);

        $this->markTravelDone($driver, $carrier, $booking);

        return $booking;
    }

    // ------------------------------------------------------------------

    protected function onboardingFor(DriverProfile $driver, Carrier $carrier): ?DriverOnboarding
    {
        return DriverOnboarding::where('driver_profile_id', $driver->id)
            ->where('carrier_id', $carrier->id)
            ->first();
    }

    protected function markTravelDone(DriverProfile $driver, Carrier $carrier, TravelBooking $booking): void
    {
        $onboarding = $this->onboardingFor($driver, $carrier);

        if (! $onboarding) {
            return;
        }

        $label = $booking->provider === 'external'
            ? 'Booked outside the platform' . ($booking->booking_reference ? ' — ' . $booking->booking_reference : '')
            : trim(($booking->carrier_name ?: '') . ' ' . ($booking->flight_number ?: ''));

        $this->onboarding->completeByKey($onboarding, 'travel', $label ?: null);
    }

    /**
     * @throws ValidationException
     */
    protected function assertDriverBelongsToCarrier(DriverProfile $driver, Carrier $carrier): void
    {
        if ($driver->hired_carrier_id !== $carrier->id) {
            throw ValidationException::withMessages([
                'driver_profile_id' => ['Travel can only be arranged for a driver you have hired.'],
            ]);
        }
    }
}
