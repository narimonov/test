<?php

namespace App\Providers;

use App\Services\Fmcsa\FakeFmcsaClient;
use App\Services\Fmcsa\FmcsaClient;
use App\Services\Fmcsa\QcMobileFmcsaClient;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Support\AiResponder;
use App\Services\Support\ClaudeResponder;
use App\Services\Support\RuleBasedResponder;
use App\Services\Support\TelegramBridge;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up external services. Each sits behind an interface, so swapping one
 * only this file and the config change.
 */
class IntegrationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(FmcsaClient::class, function () {
            if (config('fmcsa.driver') === 'qcmobile') {
                return new QcMobileFmcsaClient(config('fmcsa.qcmobile'));
            }

            return new FakeFmcsaClient();
        });

        $this->app->singleton(TelegramBridge::class, function () {
            return new TelegramBridge(config('support.telegram'));
        });

        $this->app->singleton(AiResponder::class, function () {
            $rules = new RuleBasedResponder();

            if (config('support.ai.driver') === 'claude') {
                // Claude answers, and the rules answer whenever the API is unavailable.
                return new ClaudeResponder(config('support.ai.claude'), $rules);
            }

            return $rules;
        });

        $this->app->singleton(PaymentGatewayFactory::class);

        // The default gateway; a specific one is asked for through the factory.
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PaymentGatewayFactory::class)->make());
    }
}
