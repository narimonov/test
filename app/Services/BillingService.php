<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Turns a plan choice into a checkout, and a provider callback into an active
 * subscription. The gateways themselves know nothing about plans.
 */
class BillingService
{
    /** @return array{payment: Payment, redirect_url: string} */
    public function startCheckout(Carrier $carrier, string $plan, PaymentGateway $gateway): array
    {
        $tier = config("plans.tiers.{$plan}");

        if (! $tier) {
            throw ValidationException::withMessages(['plan' => ['Unknown plan.']]);
        }

        $payment = Payment::create([
            'carrier_id'   => $carrier->id,
            'provider'     => $gateway->name(),
            'plan'         => $plan,
            'amount_cents' => $tier['price_cents'],
            'currency'     => config('plans.currency'),
        ]);

        $checkout = $gateway->createCheckout($payment);

        $payment->forceFill([
            'provider_reference' => $checkout['reference'],
            'payload'            => $checkout['payload'] ?? null,
        ])->save();

        return ['payment' => $payment->fresh(), 'redirect_url' => $checkout['redirect_url']];
    }

    /**
     * Apply a gateway result. Safe to call twice — a payment that is already
     * paid is left alone, because providers retry callbacks.
     */
    public function applyResult(array $result): ?Payment
    {
        $payment = Payment::where('provider_reference', $result['reference'])->first();

        if (! $payment) {
            Log::warning('Payment callback for an unknown reference', ['reference' => $result['reference']]);

            return null;
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return $payment;
        }

        $payment->forceFill([
            'status'  => $result['status'],
            'paid_at' => $result['status'] === Payment::STATUS_PAID ? now() : null,
            'payload' => $result['payload'],
        ])->save();

        if ($result['status'] === Payment::STATUS_PAID) {
            $this->activate($payment);
        }

        return $payment->fresh();
    }

    public function activate(Payment $payment): Carrier
    {
        $carrier = $payment->carrier;

        // Renewing before the current term ends extends it rather than
        // throwing away the days already paid for.
        $start = $carrier->subscription_expires_at && $carrier->subscription_expires_at->isFuture()
            ? $carrier->subscription_expires_at
            : now();

        $carrier->forceFill([
            'subscription_plan'       => $payment->plan,
            'subscription_status'     => 'active',
            'subscription_expires_at' => $start->copy()->addMonth(),
        ])->save();

        return $carrier->fresh();
    }
}
