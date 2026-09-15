<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Local development. No external call — the checkout link points back at our
 * own confirmation route so the whole billing flow can be exercised offline.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Payment $payment): array
    {
        $reference = 'fake-' . $payment->id;

        return [
            'redirect_url' => config('app.url') . '/carrier/billing?status=success&reference=' . $reference,
            'reference'    => $reference,
            'payload'      => ['note' => 'Development gateway — no money moves.'],
        ];
    }

    public function handleCallback(array $request, array $headers = []): array
    {
        return [
            'reference' => $request['reference'] ?? '',
            'status'    => $request['status'] ?? Payment::STATUS_PAID,
            'payload'   => $request,
        ];
    }
}
