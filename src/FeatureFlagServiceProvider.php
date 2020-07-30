<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use PhilipRehberger\FeatureFlags\Commands\FeatureDisableCommand;
use PhilipRehberger\FeatureFlags\Commands\FeatureEnableCommand;
use PhilipRehberger\FeatureFlags\Commands\FeatureListCommand;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use PhilipRehberger\FeatureFlags\Drivers\ConfigDriver;
use PhilipRehberger\FeatureFlags\Drivers\DatabaseDriver;
use PhilipRehberger\FeatureFlags\Middleware\FeatureFlagMiddleware;

class FeatureFlagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/feature-flags.php',
            'feature-flags',
        );

        $this->app->singleton(FeatureDriver::class, function ($app): FeatureDriver {
            $driver = config('feature-flags.driver', 'config');

            return match ($driver) {
                'database' => new DatabaseDriver($app['db']->connection()),
                default => new ConfigDriver(config('feature-flags.features', [])),
            };
        });

        $this->app->singleton(FeatureManager::class, function ($app): FeatureManager {
            return new FeatureManager($app->make(FeatureDriver::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/feature-flags.php' => config_path('feature-flags.php'),
        ], 'feature-flags-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'feature-flags-migrations');

        $this->registerBladeDirectives();
        $this->registerMiddlewareAlias();
        $this->registerCommands();
    }

    private function registerBladeDirectives(): void
    {
        // @feature('name') ... @elsefeature ... @endfeature
        // Blade::if() registers @feature, @elsefeature, and @endfeature automatically.
        // The closure receives no arguments when called from @elsefeature, so the
        // parameter must be optional; in that case we default to inactive (false).
        Blade::if('feature', function (string $feature = ''): bool {
            if ($feature === '') {
                return false;
            }

            return app(FeatureManager::class)->active($feature);
        });

        // @featurefor('name', $user) ... @endfeaturefor
        Blade::directive('featurefor', function (string $expression): string {
            // Expression format: 'feature-name', $user
            [$feature, $user] = array_map('trim', explode(',', $expression, 2));

            return "<?php if (app(\PhilipRehberger\FeatureFlags\FeatureManager::class)->for({$user})->active({$feature})): ?>";
        });

        Blade::directive('endfeaturefor', function (): string {
            return '<?php endif; ?>';
        });
    }

    private function registerMiddlewareAlias(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('feature', FeatureFlagMiddleware::class);
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FeatureListCommand::class,
                FeatureEnableCommand::class,
                FeatureDisableCommand::class,
            ]);
        }
    }
}
