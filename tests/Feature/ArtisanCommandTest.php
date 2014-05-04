<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use PhilipRehberger\FeatureFlags\Tests\TestCase;

class ArtisanCommandTest extends TestCase
{
    private function insertFlag(string $name, bool $active = true): void
    {
        DB::table('feature_flags')->insert([
            'name' => $name,
            'active' => $active,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // feature:list
    // -------------------------------------------------------------------------

    public function test_feature_list_shows_no_flags_message_when_empty(): void
    {
        $this->app['config']->set('feature-flags.driver', 'config');
        $this->app['config']->set('feature-flags.features', []);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('No feature flags defined');
    }

    public function test_feature_list_shows_all_flags_from_config(): void
    {
        $this->app['config']->set('feature-flags.driver', 'config');
        $this->app['config']->set('feature-flags.features', [
            'flag-one' => true,
            'flag-two' => false,
        ]);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('flag-one')
            ->expectsOutputToContain('flag-two');
    }

    public function test_feature_list_shows_all_flags_from_database(): void
    {
        $this->app['config']->set('feature-flags.driver', 'database');
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->insertFlag('db-flag-a', active: true);
        $this->insertFlag('db-flag-b', active: false);

        $this->artisan('feature:list')
            ->assertSuccessful()
            ->expectsOutputToContain('db-flag-a')
            ->expectsOutputToContain('db-flag-b');
    }

    // -------------------------------------------------------------------------
    // feature:enable
    // -------------------------------------------------------------------------

    public function test_feature_enable_enables_flag_in_database(): void
    {
        $this->app['config']->set('feature-flags.driver', 'database');
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->insertFlag('togglable', active: false);

        $this->artisan('feature:enable', ['name' => 'togglable'])
            ->assertSuccessful()
            ->expectsOutputToContain('enabled');

        $this->assertTrue((bool) DB::table('feature_flags')->where('name', 'togglable')->value('active'));
    }

    public function test_feature_enable_fails_with_error_for_config_driver(): void
    {
        $this->app['config']->set('feature-flags.driver', 'config');
        $this->app['config']->set('feature-flags.features', []);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->artisan('feature:enable', ['name' => 'any'])
            ->assertFailed();
    }

    // -------------------------------------------------------------------------
    // feature:disable
    // -------------------------------------------------------------------------

    public function test_feature_disable_disables_flag_in_database(): void
    {
        $this->app['config']->set('feature-flags.driver', 'database');
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->insertFlag('togglable', active: true);

        $this->artisan('feature:disable', ['name' => 'togglable'])
            ->assertSuccessful()
            ->expectsOutputToContain('disabled');

        $this->assertFalse((bool) DB::table('feature_flags')->where('name', 'togglable')->value('active'));
    }

    public function test_feature_disable_fails_with_error_for_config_driver(): void
    {
        $this->app['config']->set('feature-flags.driver', 'config');
        $this->app['config']->set('feature-flags.features', []);
        $this->app->forgetInstance(FeatureDriver::class);
        $this->app->forgetInstance(\PhilipRehberger\FeatureFlags\FeatureManager::class);

        $this->artisan('feature:disable', ['name' => 'any'])
            ->assertFailed();
    }
}
