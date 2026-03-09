<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Feature Flags Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "config", "database"
    |
    | The "config" driver reads flag definitions directly from the "features"
    | array below and is ideal for flags that are deployed with your code.
    |
    | The "database" driver reads flags from the "feature_flags" table, which
    | allows toggling at runtime without a deployment. Run the migration first:
    |
    |   php artisan migrate
    |
    */

    'driver' => env('FEATURE_FLAGS_DRIVER', 'config'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags (config driver)
    |--------------------------------------------------------------------------
    |
    | Define your feature flags here when using the config driver.
    |
    | Simple boolean flag:
    |   'new-checkout' => true,
    |
    | Flag with percentage rollout (25% of users will see this feature):
    |   'beta-dashboard' => ['active' => true, 'rollout' => 25],
    |
    | Flag with scheduling (only active between the given dates):
    |   'holiday-banner' => [
    |       'active' => true,
    |       'enabled_from' => '2026-12-01',
    |       'enabled_until' => '2026-12-31',
    |   ],
    |
    | Combined rollout + scheduling:
    |   'early-access' => [
    |       'active' => true,
    |       'rollout' => 10,
    |       'enabled_from' => '2026-06-01',
    |   ],
    |
    */

    'features' => [
        // 'new-checkout' => true,
        // 'beta-dashboard' => ['active' => true, 'rollout' => 25],
        // 'holiday-banner' => ['active' => true, 'enabled_from' => '2026-12-01', 'enabled_until' => '2026-12-31'],
    ],

];
