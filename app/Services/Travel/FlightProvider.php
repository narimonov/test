<?php

namespace App\Services\Travel;

interface FlightProvider
{
    /**
     * @return FlightOffer[]
     */
    public function search(string $origin, string $destination, string $departOn, array $passenger = []): array;

    /**
     * Book an offer.
     *
     * @return array{reference: string, booking_reference: string|null, status: string, payload: array}
     */
    public function book(string $offerId, array $passenger): array;

    public function name(): string;
}
