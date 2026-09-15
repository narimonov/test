<?php

namespace Tests\Unit;

use App\Models\DriverProfile;
use App\Services\DriverScoringService;
use Tests\TestCase;

class DriverScoringServiceTest extends TestCase
{
    /** Testlar config faylidan mustaqil bo'lishi uchun o'z kichik konfigi bilan ishlaydi. */
    protected function config(array $overrides = []): array
    {
        return array_merge([
            'knockouts' => [
                ['key' => 'cdl_class', 'operator' => 'in', 'value' => ['A'], 'reason' => 'CDL A emas'],
                ['key' => 'years_experience', 'operator' => 'gte', 'value' => 1, 'reason' => 'Tajriba kam'],
                ['key' => 'can_pass_drug_test', 'operator' => 'is_true', 'reason' => 'Drug test'],
            ],
            'criteria' => [
                [
                    'key' => 'years_experience', 'label' => 'Tajriba', 'type' => 'bands', 'weight' => 60,
                    'bands' => [
                        ['min' => 5, 'points' => 100],
                        ['min' => 2, 'points' => 60],
                        ['min' => 0, 'points' => 0],
                    ],
                ],
                [
                    'key' => 'accidents_3y', 'label' => 'Avariya', 'type' => 'bands', 'weight' => 40,
                    'bands' => [
                        ['max' => 0, 'points' => 100],
                        ['max' => 1, 'points' => 50],
                        ['points' => 0],
                    ],
                ],
            ],
            'tiers' => ['A' => 85, 'B' => 70, 'C' => 50, 'D' => 0],
        ], $overrides);
    }

    protected function driver(array $attributes = []): DriverProfile
    {
        return new DriverProfile(array_merge([
            'first_name'         => 'Test',
            'last_name'          => 'Driver',
            'cdl_class'          => 'A',
            'years_experience'   => 6,
            'accidents_3y'       => 0,
            'can_pass_drug_test' => true,
        ], $attributes));
    }

    public function test_it_scores_a_clean_experienced_driver_at_the_top()
    {
        $result = (new DriverScoringService($this->config()))->score($this->driver());

        $this->assertSame(100, $result['score']);
        $this->assertSame('A', $result['tier']);
        $this->assertFalse($result['disqualified']);
        $this->assertEmpty($result['knockouts']);
    }

    public function test_it_weights_criteria_proportionally()
    {
        // Tajriba 100 ball (vazn 60), avariya 50 ball (vazn 40) => (60*100 + 40*50) / 100 = 80
        $result = (new DriverScoringService($this->config()))->score($this->driver(['accidents_3y' => 1]));

        $this->assertSame(80, $result['score']);
        $this->assertSame('B', $result['tier']);
    }

    public function test_knockout_disqualifies_and_clears_tier()
    {
        $result = (new DriverScoringService($this->config()))->score($this->driver([
            'cdl_class'          => 'B',
            'can_pass_drug_test' => false,
        ]));

        $this->assertTrue($result['disqualified']);
        $this->assertNull($result['tier']);
        $this->assertEquals(['CDL A emas', 'Drug test'], $result['knockouts']);
    }

    public function test_missing_value_does_not_silently_pass_a_knockout()
    {
        $driver = $this->driver();
        $driver->years_experience = null;

        $result = (new DriverScoringService($this->config()))->score($driver);

        $this->assertTrue($result['disqualified']);
        $this->assertContains('Tajriba kam', $result['knockouts']);
    }

    public function test_overrides_replace_weights_without_touching_other_rules()
    {
        $service = (new DriverScoringService($this->config()))
            ->withOverrides(['criteria' => [['key' => 'years_experience', 'weight' => 0]]]);

        // Tajriba vazni 0 bo'lgach faqat avariya hisoblanadi.
        $result = $service->score($this->driver(['accidents_3y' => 1]));

        $this->assertSame(50, $result['score']);
    }

    public function test_job_level_override_can_relax_a_knockout()
    {
        $service = (new DriverScoringService($this->config()))
            ->withOverrides(['knockouts' => [
                ['key' => 'cdl_class', 'operator' => 'in', 'value' => ['A', 'B'], 'reason' => 'CDL A/B emas'],
            ]]);

        $result = $service->score($this->driver(['cdl_class' => 'B']));

        $this->assertFalse($result['disqualified']);
    }

    public function test_set_criteria_caps_at_max_points()
    {
        $config = $this->config([
            'criteria' => [[
                'key' => 'endorsements', 'label' => 'Endorsement', 'type' => 'set', 'weight' => 100,
                'valuable' => ['hazmat' => 60, 'tanker' => 60], 'max_points' => 100,
            ]],
        ]);

        $result = (new DriverScoringService($config))
            ->score($this->driver(['endorsements' => ['hazmat', 'tanker']]));

        $this->assertSame(100, $result['score']);
    }

    public function test_ranking_puts_disqualified_drivers_last_regardless_of_score()
    {
        $service = new DriverScoringService($this->config());

        $ranked = $service->rank([
            $this->driver(['first_name' => 'Knocked', 'can_pass_drug_test' => false]),
            $this->driver(['first_name' => 'Weak', 'years_experience' => 2, 'accidents_3y' => 2]),
            $this->driver(['first_name' => 'Strong']),
        ]);

        $this->assertSame(['Strong', 'Weak', 'Knocked'], array_map(
            fn (array $row) => $row['driver']->first_name,
            $ranked
        ));
    }
}
