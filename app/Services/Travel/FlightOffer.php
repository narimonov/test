<?php

namespace App\Services\Travel;

/**
 * One bookable flight, in the same shape whichever provider returned it.
 */
class FlightOffer
{
    public $id;
    public $origin;
    public $destination;
    public $departsAt;
    public $arrivesAt;
    public $airline;
    public $flightNumber;
    public $amountCents;
    public $currency = 'USD';
    public $stops = 0;
    public $expiresAt;
    public $raw = [];

    public static function fromArray(array $data): self
    {
        $offer = new self();

        foreach ([
            'id', 'origin', 'destination', 'departsAt', 'arrivesAt',
            'airline', 'flightNumber', 'amountCents', 'currency', 'stops', 'expiresAt',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $offer->{$key} = $data[$key];
            }
        }

        $offer->raw = $data['raw'] ?? $data;

        return $offer;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'origin'        => $this->origin,
            'destination'   => $this->destination,
            'departs_at'    => $this->departsAt,
            'arrives_at'    => $this->arrivesAt,
            'airline'       => $this->airline,
            'flight_number' => $this->flightNumber,
            'amount_cents'  => $this->amountCents,
            'currency'      => $this->currency,
            'stops'         => $this->stops,
            'expires_at'    => $this->expiresAt,
        ];
    }
}
