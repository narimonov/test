<?php

namespace App\Services\Mvr;

use App\Models\DriverProfile;

/**
 * Local development and tests. Builds a record from what the driver already
 * declared, so a driver who reported a clean history gets a clean record and
 * the flow behaves sensibly without any external call.
 */
class FakeMvrProvider implements MvrProvider
{
    /** @var array<string, MvrResult> */
    protected $stubs = [];

    public function stub(string $reference, MvrResult $result): void
    {
        $this->stubs[$reference] = $result;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function order(DriverProfile $driver, string $state): MvrResult
    {
        $reference = 'mvr-fake-' . $driver->id . '-' . strtolower($state);

        if (isset($this->stubs[$reference])) {
            return $this->stubs[$reference];
        }

        return MvrResult::fromArray([
            'reference'      => $reference,
            'status'         => 'completed',
            'licence_status' => $driver->license_suspended_ever ? 'suspended' : 'valid',
            'violations'     => (int) $driver->moving_violations_3y,
            'accidents'      => (int) $driver->accidents_3y,
            'suspensions'    => $driver->license_suspended_ever ? 1 : 0,
            'cost_cents'     => config('mvr.fallback_rates.' . strtoupper($state), config('mvr.default_cost_cents')),
            'raw'            => ['note' => 'Development provider — no state fee was incurred.'],
        ]);
    }

    public function fetch(string $reference): MvrResult
    {
        return $this->stubs[$reference] ?? MvrResult::fromArray([
            'reference' => $reference,
            'status'    => 'completed',
        ]);
    }

    public function rates(): array
    {
        $rates = [];

        foreach (config('mvr.fallback_rates') as $state => $cents) {
            $rates[$state] = ['cost_cents' => $cents, 'turnaround' => 'instant'];
        }

        return $rates;
    }
}
