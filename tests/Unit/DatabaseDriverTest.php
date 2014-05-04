<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhilipRehberger\FeatureFlags\Drivers\DatabaseDriver;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class DatabaseDriverTest extends TestCase
{
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new DatabaseDriver(DB::connection());
    }

    private function insertFlag(
        string $name,
        bool $active = true,
        ?int $rollout = null,
        ?string $from = null,
        ?string $until = null,
        ?string $description = null,
    ): void {
        DB::table('feature_flags')->insert([
            'name' => $name,
            'description' => $description,
            'active' => $active,
            'rollout_percentage' => $rollout,
            'enabled_from' => $from,
            'enabled_until' => $until,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function test_is_active_returns_true_for_active_flag(): void
    {
        $this->insertFlag('my-feature');

        $this->assertTrue($this->driver->isActive('my-feature'));
    }

    public function test_is_active_returns_false_for_inactive_flag(): void
    {
        $this->insertFlag('my-feature', active: false);

        $this->assertFalse($this->driver->isActive('my-feature'));
    }

    public function test_is_active_returns_false_for_undefined_flag(): void
    {
        $this->assertFalse($this->driver->isActive('undefined-flag'));
    }

    public function test_enable_creates_flag_if_not_exists(): void
    {
        $this->driver->enable('new-flag');

        $this->assertTrue($this->driver->isActive('new-flag'));
    }

    public function test_enable_activates_existing_flag(): void
    {
        $this->insertFlag('togglable', active: false);

        $this->driver->enable('togglable');

        $this->assertTrue($this->driver->isActive('togglable'));
    }

    public function test_disable_deactivates_existing_flag(): void
    {
        $this->insertFlag('togglable', active: true);

        $this->driver->disable('togglable');

        $this->assertFalse($this->driver->isActive('togglable'));
    }

    public function test_disable_creates_inactive_flag_if_not_exists(): void
    {
        $this->driver->disable('brand-new-disabled');

        $this->assertFalse($this->driver->isActive('brand-new-disabled'));
    }

    public function test_is_active_for_user_without_rollout_is_same_as_global(): void
    {
        $this->insertFlag('no-rollout', active: true);

        $user = $this->makeUser(1);

        $this->assertTrue($this->driver->isActiveForUser('no-rollout', $user));
    }

    public function test_is_active_for_user_respects_percentage_rollout(): void
    {
        $this->insertFlag('rollout-feature', active: true, rollout: 50);

        $included = null;
        $excluded = null;

        for ($i = 1; $i <= 200; $i++) {
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

        $this->assertNotNull($included);
        $this->assertNotNull($excluded);

        $this->assertTrue($this->driver->isActiveForUser('rollout-feature', $this->makeUser($included)));
        $this->assertFalse($this->driver->isActiveForUser('rollout-feature', $this->makeUser($excluded)));
    }

    public function test_scheduling_enabled_from_in_future_returns_false(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        $this->insertFlag('future-feature', active: true, from: '2026-07-01');

        $this->assertFalse($this->driver->isActive('future-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_enabled_until_in_past_returns_false(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-01'));

        $this->insertFlag('expired-feature', active: true, until: '2026-07-31');

        $this->assertFalse($this->driver->isActive('expired-feature'));

        Carbon::setTestNow(null);
    }

    public function test_scheduling_within_range_returns_true(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-15'));

        $this->insertFlag('holiday-banner', active: true, from: '2026-12-01', until: '2026-12-31');

        $this->assertTrue($this->driver->isActive('holiday-banner'));

        Carbon::setTestNow(null);
    }

    public function test_all_features_returns_all_rows(): void
    {
        $this->insertFlag('feature-a', active: true);
        $this->insertFlag('feature-b', active: false);
        $this->insertFlag('feature-c', active: true, rollout: 30, description: 'A partial rollout');

        $features = $this->driver->allFeatures();

        $this->assertArrayHasKey('feature-a', $features);
        $this->assertArrayHasKey('feature-b', $features);
        $this->assertArrayHasKey('feature-c', $features);
        $this->assertTrue($features['feature-a']['active']);
        $this->assertFalse($features['feature-b']['active']);
        $this->assertSame(30, $features['feature-c']['rollout_percentage']);
        $this->assertSame('A partial rollout', $features['feature-c']['description']);
    }

    public function test_all_features_returns_empty_array_when_no_flags(): void
    {
        $features = $this->driver->allFeatures();

        $this->assertSame([], $features);
    }
}
