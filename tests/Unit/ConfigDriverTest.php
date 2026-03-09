<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Unit;

use Carbon\Carbon;
use PhilipRehberger\FeatureFlags\Drivers\ConfigDriver;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class ConfigDriverTest extends TestCase
{
    public function test_active_returns_true_for_simple_boolean_true(): void
    {
        $driver = new ConfigDriver(['my-feature' => true]);

        $this->assertTrue($driver->isActive('my-feature'));
    }

    public function test_active_returns_false_for_simple_boolean_false(): void
    {
        $driver = new ConfigDriver(['my-feature' => false]);

        $this->assertFalse($driver->isActive('my-feature'));
    }

    public function test_active_returns_false_for_undefined_feature(): void
    {
        $driver = new ConfigDriver([]);

        $this->assertFalse($driver->isActive('nonexistent'));
    }

    public function test_active_returns_true_for_array_with_active_true(): void
    {
        $driver = new ConfigDriver(['my-feature' => ['active' => true]]);

        $this->assertTrue($driver->isActive('my-feature'));
    }

    public function test_active_returns_false_for_array_with_active_false(): void
    {
        $driver = new ConfigDriver(['my-feature' => ['active' => false]]);

        $this->assertFalse($driver->isActive('my-feature'));
    }

    public function test_active_with_rollout_returns_true_globally_when_active(): void
    {
        // Global active check ignores rollout — it checks the active flag only
        $driver = new ConfigDriver([
            'my-feature' => ['active' => true, 'rollout' => 10],
        ]);

        $this->assertTrue($driver->isActive('my-feature'));
    }

    public function test_is_active_for_user_respects_percentage_rollout(): void
    {
        // crc32('rollout-feature' . 1) → deterministic result
        // We find a user ID that is within 50% rollout
        $driver = new ConfigDriver([
            'rollout-feature' => ['active' => true, 'rollout' => 50],
        ]);

        $included = null;
        $excluded = null;

        for ($i = 1; $i <= 200; $i++) {
            $user = $this->makeUser($i);
            $hash = abs(crc32('rollout-feature'.$i));
            $bucket = $hash % 100;

            if ($included === null && $bucket < 50) {
                $included = $i;
            }

            if ($excluded === null && $bucket >= 50) {
                $excluded = $i;
            }

            if ($included !== null && $excluded !== null) {
                break;
            }
        }

        $this->assertNotNull($included, 'Could not find an included user in 200 attempts');
        $this->assertNotNull($excluded, 'Could not find an excluded user in 200 attempts');

        $this->assertTrue($driver->isActiveForUser('rollout-feature', $this->makeUser($included)));
        $this->assertFalse($driver->isActiveForUser('rollout-feature', $this->makeUser($excluded)));
    }

    public function test_rollout_is_deterministic_for_same_user(): void
    {
        $driver = new ConfigDriver([
            'stable-feature' => ['active' => true, 'rollout' => 50],
        ]);

        $user = $this->makeUser(42);

        $first = $driver->isActiveForUser('stable-feature', $user);
        $second = $driver->isActiveForUser('stable-feature', $user);

        $this->assertSame($first, $second);
    }

    public function test_is_active_for_user_with_100_percent_rollout_is_always_true(): void
    {
        $driver = new ConfigDriver([
            'full-rollout' => ['active' => true, 'rollout' => 100],
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $this->assertTrue($driver->isActiveForUser('full-rollout', $this->makeUser($i)));
        }
    }

    public function test_is_active_for_user_with_0_percent_rollout_is_always_false(): void
    {
        $driver = new ConfigDriver([
            'zero-rollout' => ['active' => true, 'rollout' => 0],
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $this->assertFalse($driver->isActiveForUser('zero-rollout', $this->makeUser($i)));
        }
    }

    public function test_scheduling_enabled_from_in_future_returns_false(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $driver = new ConfigDriver([
            'future-feature' => ['active' => true, 'enabled_from' => '2026-07-01'],
        ]);

        $this->assertFalse($driver->isActive('future-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_enabled_from_in_past_returns_true(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $driver = new ConfigDriver([
            'past-feature' => ['active' => true, 'enabled_from' => '2026-07-01'],
        ]);

        $this->assertTrue($driver->isActive('past-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_enabled_until_in_past_returns_false(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $driver = new ConfigDriver([
            'expired-feature' => ['active' => true, 'enabled_until' => '2026-07-31'],
        ]);

        $this->assertFalse($driver->isActive('expired-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_enabled_until_in_future_returns_true(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $driver = new ConfigDriver([
            'active-feature' => ['active' => true, 'enabled_until' => '2026-07-31'],
        ]);

        $this->assertTrue($driver->isActive('active-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_within_range_returns_true(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-15'));

        $driver = new ConfigDriver([
            'holiday-banner' => [
                'active' => true,
                'enabled_from' => '2026-12-01',
                'enabled_until' => '2026-12-31',
            ],
        ]);

        $this->assertTrue($driver->isActive('holiday-banner'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_outside_range_returns_false(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15'));

        $driver = new ConfigDriver([
            'holiday-banner' => [
                'active' => true,
                'enabled_from' => '2026-12-01',
                'enabled_until' => '2026-12-31',
            ],
        ]);

        $this->assertFalse($driver->isActive('holiday-banner'));

        Carbon::setTestNow(null);
    }

    public function test_all_features_returns_all_defined_flags(): void
    {
        $driver = new ConfigDriver([
            'alpha' => true,
            'beta' => false,
            'gamma' => ['active' => true, 'rollout' => 25],
        ]);

        $features = $driver->allFeatures();

        $this->assertArrayHasKey('alpha', $features);
        $this->assertArrayHasKey('beta', $features);
        $this->assertArrayHasKey('gamma', $features);
        $this->assertTrue($features['alpha']['active']);
        $this->assertFalse($features['beta']['active']);
        $this->assertSame(25, $features['gamma']['rollout_percentage']);
    }

    public function test_enable_throws_logic_exception(): void
    {
        $driver = new ConfigDriver([]);

        $this->expectException(\LogicException::class);

        $driver->enable('any-feature');
    }

    public function test_disable_throws_logic_exception(): void
    {
        $driver = new ConfigDriver([]);

        $this->expectException(\LogicException::class);

        $driver->disable('any-feature');
    }
}
