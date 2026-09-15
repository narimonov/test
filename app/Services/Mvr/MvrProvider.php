<?php

namespace App\Services\Mvr;

use App\Models\DriverProfile;

interface MvrProvider
{
    /**
     * Order a record. Some states return instantly, others take days, so the
     * result may come back pending with only a reference.
     */
    public function order(DriverProfile $driver, string $state): MvrResult;

    /** Collect a record that was pending. */
    public function fetch(string $reference): MvrResult;

    /**
     * Per-state pricing from the provider's own schedule.
     *
     * @return array<string, array{cost_cents: int, turnaround: string}>
     */
    public function rates(): array;

    public function name(): string;
}
