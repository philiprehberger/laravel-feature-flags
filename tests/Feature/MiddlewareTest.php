<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use PhilipRehberger\FeatureFlags\FeatureManager;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('feature-flags.driver', 'config');
    }

    public function test_middleware_allows_request_when_feature_is_active(): void
    {
        $this->app['config']->set('feature-flags.features', ['new-checkout' => true]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        Route::middleware('feature:new-checkout')->get('/test-allow', fn () => 'ok');

        $this->get('/test-allow')->assertStatus(200)->assertSee('ok');
    }

    public function test_middleware_blocks_request_when_feature_is_inactive(): void
    {
        $this->app['config']->set('feature-flags.features', ['new-checkout' => false]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        Route::middleware('feature:new-checkout')->get('/test-block', fn () => 'ok');

        $this->get('/test-block')->assertStatus(403);
    }

    public function test_middleware_blocks_request_when_feature_is_undefined(): void
    {
        $this->app['config']->set('feature-flags.features', []);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        Route::middleware('feature:undefined-feature')->get('/test-undefined', fn () => 'ok');

        $this->get('/test-undefined')->assertStatus(403);
    }

    public function test_middleware_uses_per_user_check_when_authenticated(): void
    {
        // Use 100% rollout so all authenticated users pass
        $this->app['config']->set('feature-flags.features', [
            'beta' => ['active' => true, 'rollout' => 100],
        ]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(FeatureManager::class);

        $user = $this->makeUser(1);

        Route::middleware('feature:beta')->get('/test-user', fn () => 'ok');

        $this->actingAs($user)->get('/test-user')->assertStatus(200);
    }
}
