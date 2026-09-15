<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\MvrReport;
use App\Models\MvrStateRate;
use App\Services\Mvr\MvrProvider;
use App\Services\Mvr\MvrResult;
use Illuminate\Validation\ValidationException;

/**
 * Ordering and reusing motor vehicle records.
 *
 * Two rules drive everything here:
 *
 *   1. No pull without recorded authorisation. The FCRA wants a written
 *      disclosure and permission; the DPPA wants a permissible use. We hold
 *      the driver's consent or we do not order.
 *   2. A record pulled in the last 30 days is shared, not re-ordered. States
 *      charge per pull and the record has not changed.
 */
class MvrService
{
    /** @var MvrProvider */
    protected $provider;

    public function __construct(MvrProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @return array{report: MvrReport, source: string, cost_cents: int}
     */
    public function obtain(DriverProfile $driver, Carrier $carrier, string $state = null): array
    {
        $this->assertMayOrder($driver);

        $state = strtoupper($state ?: (string) ($driver->cdl_state ?: $driver->state));

        if (strlen($state) !== 2) {
            throw ValidationException::withMessages([
                'state' => ['We need the state that issued the licence.'],
            ]);
        }

        if ($existing = $this->reusableReport($driver, $state)) {
            $this->share($existing, $carrier, 'reused');

            return ['report' => $existing, 'source' => 'reused', 'cost_cents' => 0];
        }

        return $this->order($driver, $carrier, $state);
    }

    /** A completed record for this state that is still inside the reuse window. */
    public function reusableReport(DriverProfile $driver, string $state): ?MvrReport
    {
        $window = now()->subDays((int) config('mvr.reuse_window_days', 30));

        return MvrReport::where('driver_profile_id', $driver->id)
            ->where('state', strtoupper($state))
            ->where('status', MvrReport::STATUS_COMPLETED)
            ->where('completed_at', '>=', $window)
            ->latest('completed_at')
            ->first();
    }

    /** Price for a state, from synced rates, then the configured fallback. */
    public function costFor(string $state): int
    {
        $state = strtoupper($state);

        $rate = MvrStateRate::where('provider', $this->provider->name())
            ->where('state', $state)
            ->value('cost_cents');

        return (int) ($rate
            ?? config('mvr.fallback_rates.' . $state)
            ?? config('mvr.default_cost_cents'));
    }

    /** Pull the provider's price list into our own table. */
    public function syncRates(): int
    {
        $rates = $this->provider->rates();

        foreach ($rates as $state => $rate) {
            MvrStateRate::updateOrCreate(
                ['provider' => $this->provider->name(), 'state' => strtoupper($state)],
                [
                    'cost_cents' => $rate['cost_cents'],
                    'turnaround' => $rate['turnaround'] ?? null,
                    'synced_at'  => now(),
                ]
            );
        }

        return count($rates);
    }

    /** Collect a record that came back pending. */
    public function refresh(MvrReport $report): MvrReport
    {
        if ($report->status !== MvrReport::STATUS_ORDERED || ! $report->provider_reference) {
            return $report;
        }

        return $this->applyResult($report, $this->provider->fetch($report->provider_reference));
    }

    // ------------------------------------------------------------------

    /**
     * @throws ValidationException when we have no authorisation on file
     */
    protected function assertMayOrder(DriverProfile $driver): void
    {
        $consented = $driver->user && $driver->user->mvr_consent_at !== null;

        if (! $consented) {
            throw ValidationException::withMessages([
                'driver_profile_id' => [
                    'This driver has not authorised a motor vehicle record check. '
                    . 'The FCRA and the DPPA both require written permission before a record is pulled — '
                    . 'ask them to authorise it on their privacy page first.',
                ],
            ]);
        }

        if (! $driver->cdl_number) {
            throw ValidationException::withMessages([
                'driver_profile_id' => ['We need the licence number before a record can be ordered.'],
            ]);
        }
    }

    protected function order(DriverProfile $driver, Carrier $carrier, string $state): array
    {
        $cost = $this->costFor($state);

        $report = MvrReport::create([
            'driver_profile_id'     => $driver->id,
            'ordered_by_carrier_id' => $carrier->id,
            'provider'              => $this->provider->name(),
            'state'                 => $state,
            'licence_number_last4'  => $driver->cdl_number_last4,
            'status'                => MvrReport::STATUS_ORDERED,
            'ordered_at'            => now(),
            'cost_cents'            => $cost,
        ]);

        $report = $this->applyResult($report, $this->provider->order($driver, $state));

        $this->share($report, $carrier, 'ordered');

        return [
            'report'     => $report,
            'source'     => 'ordered',
            'cost_cents' => $report->cost_cents ?? $cost,
        ];
    }

    protected function applyResult(MvrReport $report, MvrResult $result): MvrReport
    {
        $report->forceFill(array_filter([
            'provider_reference' => $result->reference,
            'status'             => $result->status === 'completed'
                ? MvrReport::STATUS_COMPLETED
                : ($result->status === 'failed' ? MvrReport::STATUS_FAILED : MvrReport::STATUS_ORDERED),
            'completed_at'       => $result->status === 'completed' ? now() : null,
            'licence_status'     => $result->licenceStatus,
            'violations_count'   => $result->violations,
            'accidents_count'    => $result->accidents,
            'suspensions_count'  => $result->suspensions,
            'cost_cents'         => $result->costCents ?? $report->cost_cents,
            'payload'            => $result->raw,
            'failure_reason'     => $result->failureReason,
        ], fn ($value) => $value !== null))->save();

        return $report->fresh();
    }

    protected function share(MvrReport $report, Carrier $carrier, string $source): void
    {
        $report->shares()->firstOrCreate(
            ['carrier_id' => $carrier->id],
            ['source' => $source]
        );
    }
}
