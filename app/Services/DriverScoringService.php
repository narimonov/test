<?php

namespace App\Services;

use App\Models\DriverProfile;
use Carbon\Carbon;

/**
 * Driverni config/driver_scoring.php dagi kriteriyalar bo'yicha baholaydi.
 *
 * Natija:
 *   [
 *     'score'        => 0..100,
 *     'tier'         => 'A'|'B'|'C'|'D'|null,
 *     'disqualified' => bool,
 *     'knockouts'    => ['CDL Class A emas', ...],
 *     'breakdown'    => [['key','label','group','value','points','weight','contribution'], ...],
 *   ]
 */
class DriverScoringService
{
    /** @var array */
    protected $config;

    public function __construct(array $config = null)
    {
        $this->config = $config ?: config('driver_scoring');
    }

    /**
     * Carrier yoki job post o'z sozlamalari bilan default config ustidan yozishi mumkin.
     */
    public function withOverrides(?array $overrides): self
    {
        if (empty($overrides)) {
            return $this;
        }

        $config = $this->config;

        if (isset($overrides['knockouts'])) {
            $config['knockouts'] = $this->mergeRules($config['knockouts'], $overrides['knockouts'], 'key');
        }

        if (isset($overrides['criteria'])) {
            $config['criteria'] = $this->mergeRules($config['criteria'], $overrides['criteria'], 'key');
        }

        if (isset($overrides['tiers'])) {
            $config['tiers'] = array_merge($config['tiers'], $overrides['tiers']);
        }

        return new static($config);
    }

    public function score(DriverProfile $driver): array
    {
        $values = $this->extractValues($driver);

        $knockouts = $this->evaluateKnockouts($values);
        $breakdown = $this->evaluateCriteria($values);

        $totalWeight = array_sum(array_column($breakdown, 'weight'));
        $score = $totalWeight > 0
            ? (int) round(array_sum(array_column($breakdown, 'contribution')) / $totalWeight)
            : 0;

        return [
            'score'        => $score,
            'tier'         => $knockouts ? null : $this->tierFor($score),
            'disqualified' => (bool) $knockouts,
            'knockouts'    => $knockouts,
            'breakdown'    => $breakdown,
        ];
    }

    /**
     * Ro'yxatni ball bo'yicha tartiblash. Disqualified bo'lganlar oxirida turadi.
     *
     * @param  \Illuminate\Support\Collection|DriverProfile[]  $drivers
     */
    public function rank($drivers): array
    {
        $scored = [];

        foreach ($drivers as $driver) {
            $result = $this->score($driver);
            $result['driver'] = $driver;
            $scored[] = $result;
        }

        usort($scored, function ($a, $b) {
            if ($a['disqualified'] !== $b['disqualified']) {
                return $a['disqualified'] ? 1 : -1;
            }

            return $b['score'] <=> $a['score'];
        });

        return $scored;
    }

    /** UI kriteriyalarni ko'rsatishi uchun. */
    public function criteriaSummary(): array
    {
        $totalWeight = array_sum(array_column($this->config['criteria'], 'weight'));

        return [
            'knockouts' => array_map(function ($rule) {
                return [
                    'key'      => $rule['key'],
                    'operator' => $rule['operator'],
                    'value'    => $rule['value'] ?? null,
                    'reason'   => $rule['reason'] ?? $rule['key'],
                ];
            }, $this->config['knockouts']),
            'criteria' => array_map(function ($rule) use ($totalWeight) {
                return [
                    'key'         => $rule['key'],
                    'label'       => $rule['label'] ?? $rule['key'],
                    'group'       => $rule['group'] ?? 'other',
                    'type'        => $rule['type'],
                    'weight'      => $rule['weight'],
                    'weight_pct'  => $totalWeight > 0 ? round($rule['weight'] / $totalWeight * 100, 1) : 0,
                ];
            }, $this->config['criteria']),
            'tiers' => $this->config['tiers'],
        ];
    }

    // ------------------------------------------------------------------
    // Ichki logika
    // ------------------------------------------------------------------

    protected function extractValues(DriverProfile $driver): array
    {
        $values = $driver->attributesToArray();

        foreach (['endorsements', 'equipment_experience'] as $jsonKey) {
            $values[$jsonKey] = $driver->{$jsonKey} ?: [];
        }

        return $values;
    }

    protected function evaluateKnockouts(array $values): array
    {
        $failed = [];

        foreach ($this->config['knockouts'] as $rule) {
            if (! $this->passes($rule, $values[$rule['key']] ?? null)) {
                $failed[] = $rule['reason'] ?? $rule['key'];
            }
        }

        return $failed;
    }

    protected function passes(array $rule, $value): bool
    {
        $expected = $rule['value'] ?? null;

        switch ($rule['operator']) {
            case 'gte': return $value !== null && $value >= $expected;
            case 'lte': return $value !== null && $value <= $expected;
            case 'gt':  return $value !== null && $value > $expected;
            case 'lt':  return $value !== null && $value < $expected;
            case 'eq':  return $value == $expected;
            case 'neq': return $value != $expected;
            case 'in':  return in_array($value, (array) $expected, false);
            case 'not_in': return ! in_array($value, (array) $expected, false);
            case 'is_true':  return (bool) $value === true;
            case 'is_false': return (bool) $value === false;
            case 'date_after_today':
                return $value !== null && Carbon::parse($value)->endOfDay()->isFuture();
            default:
                return true;
        }
    }

    protected function evaluateCriteria(array $values): array
    {
        $breakdown = [];

        foreach ($this->config['criteria'] as $rule) {
            $value = $values[$rule['key']] ?? null;
            $points = $this->pointsFor($rule, $value);

            $breakdown[] = [
                'key'          => $rule['key'],
                'label'        => $rule['label'] ?? $rule['key'],
                'group'        => $rule['group'] ?? 'other',
                'value'        => $value,
                'points'       => $points,
                'weight'       => $rule['weight'],
                'contribution' => $points * $rule['weight'],
            ];
        }

        return $breakdown;
    }

    protected function pointsFor(array $rule, $value): float
    {
        $isEmptySet = in_array($rule['type'], ['set'], true) && empty($value);

        if ($value === null || $isEmptySet) {
            return (float) ($rule['null_points'] ?? 0);
        }

        switch ($rule['type']) {
            case 'bands':
                return $this->bandPoints($rule['bands'], $value);

            case 'map':
                $map = $rule['map'];
                $key = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

                return (float) ($map[$key] ?? $map['_default'] ?? 0);

            case 'boolean':
                return (float) ($value
                    ? ($rule['true_points'] ?? 100)
                    : ($rule['false_points'] ?? 0));

            case 'set':
                $earned = 0;
                foreach ((array) $value as $item) {
                    $earned += $rule['valuable'][$item] ?? ($rule['points_per_match'] ?? 0);
                }

                return (float) min($earned, $rule['max_points'] ?? 100);

            default:
                return 0.0;
        }
    }

    protected function bandPoints(array $bands, $value): float
    {
        $value = (float) $value;

        foreach ($bands as $band) {
            $minOk = ! isset($band['min']) || $value >= $band['min'];
            $maxOk = ! isset($band['max']) || $value <= $band['max'];

            if ($minOk && $maxOk) {
                return (float) $band['points'];
            }
        }

        return 0.0;
    }

    protected function tierFor(int $score): string
    {
        $tiers = $this->config['tiers'];
        arsort($tiers);

        foreach ($tiers as $tier => $threshold) {
            if ($score >= $threshold) {
                return $tier;
            }
        }

        return (string) array_key_last($tiers);
    }

    /** Override qoidalarini 'key' bo'yicha almashtiradi, yangilarini qo'shadi. */
    protected function mergeRules(array $base, array $overrides, string $matchOn): array
    {
        $indexed = [];
        foreach ($base as $rule) {
            $indexed[$rule[$matchOn]] = $rule;
        }

        foreach ($overrides as $rule) {
            if (! isset($rule[$matchOn])) {
                continue;
            }

            // weight = 0 yoki 'disabled' => true bo'lsa qoidani o'chiradi.
            if (! empty($rule['disabled'])) {
                unset($indexed[$rule[$matchOn]]);
                continue;
            }

            $indexed[$rule[$matchOn]] = isset($indexed[$rule[$matchOn]])
                ? array_merge($indexed[$rule[$matchOn]], $rule)
                : $rule;
        }

        return array_values($indexed);
    }
}
