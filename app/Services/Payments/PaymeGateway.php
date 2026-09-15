<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

/**
 * Payme (Paycom) Merchant API.
 *
 * Checkout is a base64 parameter string on the hosted page. Payme then calls
 * us back over JSON-RPC with HTTP Basic auth, where the user is the literal
 * word "Paycom" and the password is the merchant key.
 *
 * Amounts are sent in tiyin (1 UZS = 100 tiyin).
 */
class PaymeGateway implements PaymentGateway
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'payme';
    }

    public function createCheckout(Payment $payment): array
    {
        $params = implode(';', [
            'm=' . $this->config['merchant_id'],
            'ac.payment_id=' . $payment->id,
            'a=' . $this->tiyin($payment),
            'c=' . config('app.url') . '/carrier/billing',
        ]);

        return [
            'redirect_url' => rtrim($this->config['checkout_url'], '/') . '/' . base64_encode($params),
            'reference'    => 'payme-' . $payment->id,
            'payload'      => ['params' => $params],
        ];
    }

    public function handleCallback(array $request, array $headers = []): array
    {
        $this->assertAuthorised($headers);

        $method = $request['method'] ?? '';
        $account = $request['params']['account'] ?? [];
        $paymentId = $account['payment_id'] ?? null;

        // Payme drives the transaction through several calls; only
        // PerformTransaction means the money actually moved.
        $status = [
            'PerformTransaction' => Payment::STATUS_PAID,
            'CancelTransaction'  => Payment::STATUS_CANCELLED,
        ][$method] ?? Payment::STATUS_PENDING;

        return [
            'reference' => 'payme-' . $paymentId,
            'status'    => $status,
            'payload'   => $request,
        ];
    }

    protected function assertAuthorised(array $headers): void
    {
        $header = $headers['authorization'][0] ?? ($headers['authorization'] ?? '');

        if (is_array($header)) {
            $header = reset($header);
        }

        $decoded = base64_decode((string) preg_replace('/^Basic\s+/i', '', (string) $header), true);
        $expected = 'Paycom:' . ($this->config['key'] ?? '');

        if (! $decoded || ! hash_equals($expected, $decoded)) {
            throw new RuntimeException('Payme authorisation failed.');
        }
    }

    /** USD cents -> UZS tiyin, using the configured rate. */
    protected function tiyin(Payment $payment): int
    {
        if (strtoupper($payment->currency) === 'UZS') {
            return $payment->amount_cents;
        }

        return (int) round($payment->amount_cents / 100 * config('payments.uzs_rate') * 100);
    }
}
