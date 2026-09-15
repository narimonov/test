<?php

namespace App\Services\Mvr;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SambaSafety.
 *
 * Their demo environment accepts the same calls without incurring state fees,
 * so the whole order-and-collect path can be exercised before go-live.
 */
class SambaSafetyProvider implements MvrProvider
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function name(): string
    {
        return 'sambasafety';
    }

    public function order(DriverProfile $driver, string $state): MvrResult
    {
        $response = $this->request()->post($this->url('mvr/orders'), [
            'accountId'     => $this->config['account_id'],
            'state'         => strtoupper($state),
            'licenseNumber' => $driver->cdl_number,
            'firstName'     => $driver->first_name,
            'lastName'      => $driver->last_name,
            'dateOfBirth'   => optional($driver->date_of_birth)->toDateString(),
        ]);

        if (! $response->successful()) {
            Log::warning('MVR order failed', ['status' => $response->status(), 'state' => $state]);

            return MvrResult::fromArray([
                'status'         => 'failed',
                'failure_reason' => 'Provider rejected the order (' . $response->status() . ').',
            ]);
        }

        return $this->toResult($response->json());
    }

    public function fetch(string $reference): MvrResult
    {
        $response = $this->request()->get($this->url("mvr/orders/{$reference}"));

        if (! $response->successful()) {
            return MvrResult::fromArray([
                'reference'      => $reference,
                'status'         => 'failed',
                'failure_reason' => 'Could not collect the record (' . $response->status() . ').',
            ]);
        }

        return $this->toResult($response->json());
    }

    public function rates(): array
    {
        // Pricing changes rarely; a day of cache spares the provider a call per page view.
        return Cache::remember('mvr.rates.sambasafety', now()->addDay(), function () {
            $response = $this->request()->get($this->url('mvr/pricing'), [
                'accountId' => $this->config['account_id'],
            ]);

            if (! $response->successful()) {
                return [];
            }

            $rates = [];

            foreach ($response->json('states', []) as $row) {
                $rates[strtoupper($row['state'])] = [
                    'cost_cents' => (int) round(((float) $row['price']) * 100),
                    'turnaround' => $row['turnaround'] ?? null,
                ];
            }

            return $rates;
        });
    }

    // ------------------------------------------------------------------

    protected function request()
    {
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new RuntimeException('MVR credentials are not configured.');
        }

        return Http::withBasicAuth($this->config['client_id'], $this->config['client_secret'])
            ->timeout($this->config['timeout'] ?? 30)
            ->acceptJson();
    }

    protected function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/') . '/' . $path;
    }

    protected function toResult(array $payload): MvrResult
    {
        $report = $payload['report'] ?? $payload;

        return MvrResult::fromArray([
            'reference'      => $payload['orderId'] ?? ($payload['id'] ?? null),
            'status'         => ($payload['status'] ?? 'completed') === 'COMPLETE' ? 'completed' : 'pending',
            'licence_status' => strtolower((string) ($report['licenseStatus'] ?? '')) ?: null,
            'violations'     => count($report['violations'] ?? []),
            'accidents'      => count($report['accidents'] ?? []),
            'suspensions'    => count($report['suspensions'] ?? []),
            'cost_cents'     => isset($payload['price']) ? (int) round(((float) $payload['price']) * 100) : null,
            'raw'            => $payload,
        ]);
    }
}
