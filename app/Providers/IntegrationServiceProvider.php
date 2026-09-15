<?php

namespace App\Providers;

use App\Services\Fmcsa\FakeFmcsaClient;
use App\Services\Fmcsa\FmcsaClient;
use App\Services\Fmcsa\QcMobileFmcsaClient;
use App\Services\Mvr\FakeMvrProvider;
use App\Services\Mvr\MvrProvider;
use App\Services\Mvr\SambaSafetyProvider;
use App\Services\Reputation\FakeReputationSource;
use App\Services\Reputation\FmcsaSafetySource;
use App\Services\Reputation\GooglePlacesSource;
use App\Services\Travel\DuffelProvider;
use App\Services\Travel\FakeFlightProvider;
use App\Services\Travel\FlightProvider;
use App\Services\CarrierReputationService;
use App\Services\ReputationService;
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

        $this->app->singleton(MvrProvider::class, function () {
            if (config('mvr.driver') === 'sambasafety') {
                return new SambaSafetyProvider(config('mvr.providers.sambasafety'));
            }

            return new FakeMvrProvider();
        });

        $this->app->singleton(FlightProvider::class, function () {
            if (config('travel.driver') === 'duffel') {
                return new DuffelProvider(config('travel.providers.duffel'));
            }

            return new FakeFlightProvider();
        });

        $this->app->singleton(CarrierReputationService::class, function ($app) {
            return new CarrierReputationService(
                $this->reputationSources($app),
                $app->make(ReputationService::class)
            );
        });

        $this->app->singleton(PaymentGatewayFactory::class);

        // The default gateway; a specific one is asked for through the factory.
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PaymentGatewayFactory::class)->make());
    }

    /**
     * Only sources that permit this kind of use are wired in — see
     * config/reputation_sources.php for why Indeed and Glassdoor are not.
     */
    protected function reputationSources($app): array
    {
        $sources = [];

        foreach (config('reputation_sources.enabled', []) as $key) {
            switch (trim($key)) {
                case 'fmcsa_safety':
                    $sources[] = new FmcsaSafetySource($app->make(FmcsaClient::class));
                    break;

                case 'google':
                    $config = config('reputation_sources.sources.google');
                    $sources[] = empty($config['api_key'])
                        ? new FakeReputationSource()
                        : new GooglePlacesSource($config);
                    break;
            }
        }

        return $sources;
    }
}
