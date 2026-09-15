<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Start a checkout and return where to send the customer.
     *
     * @return array{redirect_url: string, reference: string, payload?: array}
     */
    public function createCheckout(Payment $payment): array;

    /**
     * Turn a provider callback into a decision about the payment.
     *
     * @return array{reference: string, status: string, payload: array}
     */
    public function handleCallback(array $request, array $headers = []): array;

    public function name(): string;
}
