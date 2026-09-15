<?php

namespace App\Providers;

use App\Services\Fmcsa\FakeFmcsaClient;
use App\Services\Fmcsa\FmcsaClient;
use App\Services\Fmcsa\QcMobileFmcsaClient;
use Illuminate\Support\ServiceProvider;

/**
 * Tashqi xizmatlarni ulaydi. Har biri interfeys orqali bog'langan, shuning
 * uchun provayderni almashtirish uchun faqat shu fayl va config o'zgaradi.
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
    }
}
