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

    public function test_rule_registration_and_evaluation(): void
    {
        $manager = $this->managerWith(['gated' => true]);

        $manager->rule('gated', fn ($user) => false);

        $this->assertFalse($manager->active('gated'));
    }

    public function test_rule_returning_true_allows_active_feature(): void
    {
        $manager = $this->managerWith(['gated' => true]);

        $manager->rule('gated', fn ($user) => true);

        $this->assertTrue($manager->active('gated'));
    }

    public function test_rule_is_not_called_when_feature_is_inactive(): void
    {
        $manager = $this->managerWith(['off' => false]);

        $called = false;
        $manager->rule('off', function ($user) use (&$called) {
            $called = true;

            return true;
        });

        $this->assertFalse($manager->active('off'));
        $this->assertFalse($called);
    }

    public function test_rule_receives_null_for_global_check(): void
    {
        $manager = $this->managerWith(['checked' => true]);

        $receivedUser = 'not-called';
        $manager->rule('checked', function ($user) use (&$receivedUser) {
            $receivedUser = $user;

            return true;
        });

        $manager->active('checked');

        $this->assertNull($receivedUser);
    }

    public function test_rule_receives_user_for_per_user_check(): void
    {
        $manager = $this->managerWith(['user-gated' => true]);
        $user = $this->makeUser(42);

        $receivedUser = null;
        $manager->rule('user-gated', function ($u) use (&$receivedUser) {
            $receivedUser = $u;

            return true;
        });

        $manager->for($user)->active('user-gated');

        $this->assertSame($user, $receivedUser);
    }

    public function test_rule_combined_with_percentage_rollout(): void
    {
        // Find a user that is within the 100% rollout bucket
        $manager = $this->managerWith([
            'rollout-rule' => ['active' => true, 'rollout' => 100],
        ]);

        $user = $this->makeUser(1);

        // Rule rejects — should be false even though rollout passes
        $manager->rule('rollout-rule', fn ($u) => false);

        $this->assertFalse($manager->for($user)->active('rollout-rule'));
    }

    public function test_rule_passes_but_rollout_rejects(): void
    {
        $manager = $this->managerWith([
            'narrow-rollout' => ['active' => true, 'rollout' => 0],
        ]);

        $user = $this->makeUser(1);

        // Rule allows — but 0% rollout should still reject
        $manager->rule('narrow-rollout', fn ($u) => true);

        $this->assertFalse($manager->for($user)->active('narrow-rollout'));
    }

    public function test_rule_with_null_user_on_global_check(): void
    {
        $manager = $this->managerWith(['null-check' => true]);

        $manager->rule('null-check', fn ($user) => $user === null);

        $this->assertTrue($manager->active('null-check'));
    }

    public function test_feature_without_rule_is_unaffected(): void
    {
        $manager = $this->managerWith(['no-rule' => true, 'has-rule' => true]);

        $manager->rule('has-rule', fn ($user) => false);

        $this->assertTrue($manager->active('no-rule'));
        $this->assertFalse($manager->active('has-rule'));
    }
}
