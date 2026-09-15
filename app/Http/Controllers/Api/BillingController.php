<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function plans(Request $request, PlanGate $plans)
    {
        $carrier = $request->user()->carrier;

        return response()->json([
            'currency' => config('plans.currency'),
            'tiers'    => collect(config('plans.tiers'))->map(fn ($tier, $key) => [
                'key'         => $key,
                'name'        => $tier['name'],
                'tagline'     => $tier['tagline'],
                'price_cents' => $tier['price_cents'],
                'highlights'  => $tier['highlights'],
            ])->values(),
            'providers' => ['stripe' => 'Card (Stripe)', 'payme' => 'Payme', 'click' => 'Click'],
            'current'   => $carrier ? $plans->summary($carrier) : null,
            'payments'  => $carrier
                ? Payment::where('carrier_id', $carrier->id)->latest()->limit(10)->get()
                : [],
        ]);
    }

    public function checkout(Request $request, BillingService $billing, PaymentGatewayFactory $gateways)
    {
        $data = $request->validate([
            'plan'     => ['required', Rule::in(array_keys(config('plans.tiers')))],
            'provider' => ['nullable', Rule::in(array_keys(config('payments.gateways')))],
        ]);

        $user = $request->user();

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Verify your account before subscribing.',
                'code'    => 'verification_required',
            ], 403);
        }

        $gateway = $gateways->make($data['provider'] ?? null);

        $result = $billing->startCheckout($user->carrier, $data['plan'], $gateway);

        return response()->json([
            'payment'      => $result['payment'],
            'redirect_url' => $result['redirect_url'],
        ], 201);
    }

    /**
     * Gateway callbacks. Unauthenticated by design — each gateway verifies its
     * own signature, and an unverified callback is rejected there.
     */
    public function callback(Request $request, string $provider, BillingService $billing, PaymentGatewayFactory $gateways)
    {
        try {
            $gateway = $gateways->make($provider);

            $result = $gateway->handleCallback($request->all(), $request->headers->all());
        } catch (\Throwable $e) {
            Log::warning('Rejected a payment callback', ['provider' => $provider, 'message' => $e->getMessage()]);

            return response()->json(['ok' => false], 400);
        }

        $payment = $billing->applyResult($result);

        return response()->json(['ok' => true, 'status' => optional($payment)->status]);
    }

    /**
     * Development gateway only: confirms a payment the fake checkout started.
     */
    public function confirmFake(Request $request, BillingService $billing)
    {
        abort_unless(config('payments.default') === 'fake', 404);

        $data = $request->validate(['reference' => ['required', 'string']]);

        $payment = Payment::where('provider_reference', $data['reference'])
            ->where('carrier_id', optional($request->user()->carrier)->id)
            ->firstOrFail();

        $payment = $billing->applyResult([
            'reference' => $data['reference'],
            'status'    => Payment::STATUS_PAID,
            'payload'   => ['confirmed_by' => $request->user()->id],
        ]);

        return response()->json([
            'message' => 'Subscription activated.',
            'payment' => $payment,
            'carrier' => Carrier::find($payment->carrier_id),
        ]);
    }
}
