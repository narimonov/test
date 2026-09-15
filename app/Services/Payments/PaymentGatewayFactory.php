<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/**
 * Builds a gateway by name, so a carrier can pay with a provider other than
 * the configured default without the controller knowing any of them.
 */
class PaymentGatewayFactory
{
    public function make(?string $name = null): PaymentGateway
    {
        $name = $name ?: config('payments.default');
        $config = config("payments.gateways.{$name}", []);

        switch ($name) {
            case 'stripe': return new StripeGateway($config);
            case 'payme':  return new PaymeGateway($config);
            case 'click':  return new ClickGateway($config);
            case 'fake':   return new FakeGateway();
        }

        throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
    }
}
