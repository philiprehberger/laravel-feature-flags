<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Unit;

use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use PhilipRehberger\FeatureFlags\FeatureManager;
use PhilipRehberger\FeatureFlags\PendingFeatureCheck;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    private function managerWith(array $features): FeatureManager
    {
        config(['feature-flags.driver' => 'config', 'feature-flags.features' => $features]);

        return app(FeatureManager::class);
    }

    public function test_active_delegates_to_driver(): void
    {
        $manager = $this->managerWith(['checkout' => true]);

        $this->assertTrue($manager->active('checkout'));
        $this->assertFalse($manager->active('nonexistent'));
    }

    public function test_for_returns_pending_feature_check(): void
    {
        $manager = $this->managerWith([]);
        $user = $this->makeUser(1);

        $pending = $manager->for($user);

        $this->assertInstanceOf(PendingFeatureCheck::class, $pending);
    }

    public function test_for_active_checks_user_context(): void
    {
        config([
            'feature-flags.driver' => 'config',
            'feature-flags.features' => ['full-flag' => true],
        ]);

        // Rebind so the manager picks up the new config
        $this->app->forgetInstance(FeatureManager::class);
        $this->app->forgetInstance(FeatureDriver::class);

        $manager = app(FeatureManager::class);
        $user = $this->makeUser(1);

        $this->assertTrue($manager->for($user)->active('full-flag'));
    }

    public function test_all_features_returns_array(): void
    {
        $manager = $this->managerWith([
            'flag-a' => true,
            'flag-b' => false,
        ]);

        $features = $manager->allFeatures();

        $this->assertIsArray($features);
        $this->assertArrayHasKey('flag-a', $features);
        $this->assertArrayHasKey('flag-b', $features);
    }

    public function test_enable_and_disable_throw_for_config_driver(): void
    {
        $manager = $this->managerWith([]);

        $this->expectException(\LogicException::class);
        $manager->enable('any');
    }
}
