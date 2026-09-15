<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout. We call the REST API directly rather than pulling in the
 * SDK — one endpoint to create a session, one webhook to confirm it.
 */
class StripeGateway implements PaymentGateway
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Payment $payment): array
    {
        $plan = config("plans.tiers.{$payment->plan}");

        $response = Http::withToken($this->config['secret_key'])
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode'        => 'subscription',
                'success_url' => $this->config['success_url'],
                'cancel_url'  => $this->config['cancel_url'],
                'client_reference_id' => (string) $payment->id,
                'line_items'  => [[
                    'quantity'   => 1,
                    'price_data' => [
                        'currency'     => strtolower($payment->currency),
                        'unit_amount'  => $payment->amount_cents,
                        'recurring'    => ['interval' => 'month'],
                        'product_data' => ['name' => ($plan['name'] ?? $payment->plan) . ' plan'],
                    ],
                ]],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'carrier_id' => $payment->carrier_id,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Stripe rejected the checkout: ' . $response->body());
        }

        return [
            'redirect_url' => $response->json('url'),
            'reference'    => $response->json('id'),
            'payload'      => $response->json(),
        ];
    }

    public function handleCallback(array $request, array $headers = []): array
    {
        $this->assertSignature($headers, $request);

        $session = $request['data']['object'] ?? [];
        $event = $request['type'] ?? '';

        $status = in_array($event, ['checkout.session.completed', 'invoice.paid'], true)
            && ($session['payment_status'] ?? null) !== 'unpaid'
                ? Payment::STATUS_PAID
                : Payment::STATUS_FAILED;

        return [
            'reference' => $session['id'] ?? '',
            'status'    => $status,
            'payload'   => $request,
        ];
    }

    /**
     * Stripe signs every webhook. An unsigned or stale call is not Stripe.
     */
    protected function assertSignature(array $headers, array $request): void
    {
        $secret = $this->config['webhook_secret'] ?? null;

        if (! $secret) {
            throw new RuntimeException('STRIPE_WEBHOOK_SECRET is not configured.');
        }

        $header = $headers['stripe-signature'][0] ?? ($headers['stripe-signature'] ?? '');

        if (is_array($header)) {
            $header = reset($header);
        }

        $parts = [];
        foreach (explode(',', (string) $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || ! $signatures) {
            throw new RuntimeException('Missing Stripe signature header.');
        }

        // Reject replays of an old, valid signature.
        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Stripe signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . json_encode($request), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return;
            }
        }

        throw new RuntimeException('Stripe signature did not match.');
    }
}
