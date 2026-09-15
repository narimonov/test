<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

/**
 * Click Merchant API.
 *
 * Click posts twice: Prepare (action 0) reserves the payment, Complete
 * (action 1) confirms it. Both carry an md5 signature we have to verify —
 * the field order in that hash is fixed by Click and must not be changed.
 */
class ClickGateway implements PaymentGateway
{
    const ACTION_PREPARE = '0';
    const ACTION_COMPLETE = '1';

    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'click';
    }

    public function createCheckout(Payment $payment): array
    {
        $query = http_build_query([
            'service_id'        => $this->config['service_id'],
            'merchant_id'       => $this->config['merchant_id'],
            'amount'            => $this->soum($payment),
            'transaction_param' => $payment->id,
            'return_url'        => config('app.url') . '/carrier/billing',
        ]);

        return [
            'redirect_url' => rtrim($this->config['checkout_url'], '/') . '?' . $query,
            'reference'    => 'click-' . $payment->id,
            'payload'      => [],
        ];
    }

    public function handleCallback(array $request, array $headers = []): array
    {
        $this->assertSignature($request);

        $action = (string) ($request['action'] ?? '');
        $errorCode = (int) ($request['error'] ?? 0);

        if ($errorCode < 0) {
            $status = Payment::STATUS_CANCELLED;
        } elseif ($action === self::ACTION_COMPLETE) {
            $status = Payment::STATUS_PAID;
        } else {
            $status = Payment::STATUS_PENDING;
        }

        return [
            'reference' => 'click-' . ($request['merchant_trans_id'] ?? ''),
            'status'    => $status,
            'payload'   => $request,
        ];
    }

    /**
     * Click's signature covers a fixed sequence of fields; merchant_prepare_id
     * only takes part on the Complete call.
     */
    protected function assertSignature(array $request): void
    {
        $pieces = [
            $request['click_trans_id'] ?? '',
            $request['service_id'] ?? '',
            $this->config['secret_key'] ?? '',
            $request['merchant_trans_id'] ?? '',
        ];

        if ((string) ($request['action'] ?? '') === self::ACTION_COMPLETE) {
            $pieces[] = $request['merchant_prepare_id'] ?? '';
        }

        $pieces[] = $request['amount'] ?? '';
        $pieces[] = $request['action'] ?? '';
        $pieces[] = $request['sign_time'] ?? '';

        $expected = md5(implode('', $pieces));

        if (! hash_equals($expected, (string) ($request['sign_string'] ?? ''))) {
            throw new RuntimeException('Click signature did not match.');
        }
    }

    protected function soum(Payment $payment): float
    {
        if (strtoupper($payment->currency) === 'UZS') {
            return round($payment->amount_cents / 100, 2);
        }

        return round($payment->amount_cents / 100 * config('payments.uzs_rate'), 2);
    }
}
