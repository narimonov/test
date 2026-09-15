<?php

namespace App\Services;

use App\Models\Carrier;

/**
 * Answers "may this carrier do X on its current plan?".
 *
 * Kept apart from the plan config so that limits are asked the same way
 * everywhere, instead of each controller reading the config itself.
 */
class PlanGate
{
    public function tier(Carrier $carrier): array
    {
        return config("plans.tiers.{$carrier->subscription_plan}")
            ?? config('plans.tiers.starter');
    }

    public function allows(Carrier $carrier, string $feature): bool
    {
        if (! $carrier->has_active_subscription) {
            return false;
        }

        return (bool) ($this->tier($carrier)['features'][$feature] ?? false);
    }

    /** Null means unlimited. */
    public function maxActiveJobs(Carrier $carrier): ?int
    {
        return $this->tier($carrier)['max_active_jobs'] ?? null;
    }

    public function talentPoolLimit(Carrier $carrier): ?int
    {
        return $this->tier($carrier)['talent_pool_limit'] ?? null;
    }

    public function hasReachedJobLimit(Carrier $carrier): bool
    {
        $max = $this->maxActiveJobs($carrier);

        if ($max === null) {
            return false;
        }

        return $carrier->jobPosts()->where('is_open', true)->count() >= $max;
    }

    /** What the frontend needs to show or hide plan-gated controls. */
    public function summary(Carrier $carrier): array
    {
        $tier = $this->tier($carrier);

        return [
            'plan'              => $carrier->subscription_plan,
            'name'              => $tier['name'],
            'active'            => $carrier->has_active_subscription,
            'features'          => $tier['features'],
            'max_active_jobs'   => $tier['max_active_jobs'],
            'talent_pool_limit' => $tier['talent_pool_limit'],
            'active_jobs'       => $carrier->jobPosts()->where('is_open', true)->count(),
        ];
    }
}
