<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PhilipRehberger\FeatureFlags\FeatureFlagServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FeatureFlagServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Feature' => \PhilipRehberger\FeatureFlags\Facades\Feature::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Create a fake authenticatable user for testing.
     */
    protected function makeUser(int|string $id): Authenticatable
    {
        return new FakeUser($id);
    }
}
