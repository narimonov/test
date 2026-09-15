<?php

/*
|--------------------------------------------------------------------------
| Payment gateways
|--------------------------------------------------------------------------
|
| Stripe for cards, Payme and Click for Uzbek customers. Each one sits behind
| the same PaymentGateway interface, so adding or swapping a provider never
| touches the billing flow.
|
| driver=fake marks a payment paid immediately — local development only.
|
*/

return [

    'default' => env('PAYMENTS_DRIVER', 'fake'),

    'gateways' => [

        'stripe' => [
            'secret_key'     => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'success_url'    => env('APP_URL') . '/carrier/billing?status=success',
            'cancel_url'     => env('APP_URL') . '/carrier/billing?status=cancelled',
        ],

        // Payme Merchant API (JSON-RPC over HTTP Basic auth).
        'payme' => [
            'merchant_id' => env('PAYME_MERCHANT_ID'),
            'key'         => env('PAYME_KEY'),
            'checkout_url' => env('PAYME_CHECKOUT_URL', 'https://checkout.paycom.uz'),
        ],

        // Local development: marks a payment paid without any external call.
        'fake' => [],

        // Click Merchant API (Prepare / Complete callbacks).
        'click' => [
            'merchant_id'  => env('CLICK_MERCHANT_ID'),
            'service_id'   => env('CLICK_SERVICE_ID'),
            'secret_key'   => env('CLICK_SECRET_KEY'),
            'checkout_url' => env('CLICK_CHECKOUT_URL', 'https://my.click.uz/services/pay'),
        ],
    ],

    // Cards are billed in USD; Payme and Click settle in UZS.
    'uzs_rate' => env('PAYMENTS_UZS_RATE', 12800),
];
