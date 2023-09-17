# Laravel Feature Flags

[![Tests](https://github.com/philiprehberger/laravel-feature-flags/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/laravel-feature-flags/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/laravel-feature-flags.svg)](https://packagist.org/packages/philiprehberger/laravel-feature-flags)
[![License](https://img.shields.io/github/license/philiprehberger/laravel-feature-flags)](LICENSE)

Lightweight feature flags with config and database drivers, percentage rollout, and scheduling.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

Install via Composer:

```bash
composer require philiprehberger/laravel-feature-flags
```

The service provider and `Feature` facade are registered automatically via Laravel's package auto-discovery.

### Publish the config

```bash
php artisan vendor:publish --tag=feature-flags-config
```

### Publish and run the migration (database driver only)

```bash
php artisan vendor:publish --tag=feature-flags-migrations
php artisan migrate
```

### Configuration

`config/feature-flags.php`:

```php
return [

    // 'config' reads flags from the 'features' array below.
    // 'database' reads flags from the feature_flags table.
    'driver' => env('FEATURE_FLAGS_DRIVER', 'config'),

    'features' => [
        // Simple on/off flag
        'new-checkout' => true,

        // Partial rollout — 25% of users will see this feature
        'beta-dashboard' => ['active' => true, 'rollout' => 25],

        // Scheduled flag — only active between the two dates
        'holiday-banner' => [
            'active'        => true,
            'enabled_from'  => '2026-12-01',
            'enabled_until' => '2026-12-31',
        ],

        // Combined: rollout + scheduling
        'early-access' => [
            'active'       => true,
            'rollout'      => 10,
            'enabled_from' => '2026-06-01',
        ],
    ],

];
```

Set the driver via your `.env`:

```env
FEATURE_FLAGS_DRIVER=database
```

## Usage

### Facade

```php
use PhilipRehberger\FeatureFlags\Facades\Feature;

// Global check (no user context)
if (Feature::active('new-checkout')) {
    // ...
}

// Per-user check (respects rollout percentage)
if (Feature::for($request->user())->active('beta-dashboard')) {
    // ...
}

// List all defined flags
$flags = Feature::allFeatures();

// Enable / disable at runtime (database driver only)
Feature::enable('new-checkout');
Feature::disable('new-checkout');
```

### Custom Rules

Register a callable that must also pass for a feature to be active. Rules are evaluated after percentage rollout and scheduling. The callable receives the user (or `null` for global checks) and must return a bool:

```php
use PhilipRehberger\FeatureFlags\Facades\Feature;

// Only allow users with a verified email
Feature::rule('beta-dashboard', function (?Authenticatable $user): bool {
    return $user !== null && $user->hasVerifiedEmail();
});

// Rules combine with rollout — both must pass
// Here only verified users within the 25% rollout see the feature
Feature::for($request->user())->active('beta-dashboard');

// Global check passes null to the rule
Feature::active('beta-dashboard'); // rule receives null
```

### Blade Directives

Global check:

```blade
@feature('new-checkout')
    <x-checkout-v2 />
@endfeature
```

With an else branch (plain PHP `@else`):

```blade
@feature('new-checkout')
    <x-checkout-v2 />
@else
    <x-checkout-v1 />
@endfeature
```

Conditional on a second feature (`@elsefeature` is the elseif variant):

```blade
@feature('checkout-v3')
    <x-checkout-v3 />
@elsefeature('new-checkout')
    <x-checkout-v2 />
@endfeature
```

Per-user check (respects rollout):

```blade
@featurefor('beta-dashboard', auth()->user())
    <x-beta-dashboard />
@endfeaturefor
```

### Route Middleware

Protect a route so it returns 403 when the feature is inactive:

```php
Route::get('/checkout/v2', CheckoutV2Controller::class)
    ->middleware('feature:new-checkout');
```

When the request has an authenticated user, the per-user rollout check is applied. Otherwise the global active state is used.

### Artisan Commands

```bash
# List all feature flags with status, rollout, and schedule
php artisan feature:list

# Enable a flag (database driver only)
php artisan feature:enable new-checkout

# Disable a flag (database driver only)
php artisan feature:disable new-checkout
```

Example `feature:list` output:

```
+------------------+---------+------------+-----------+-------------+
| Name             | Status  | Rollout    | Schedule  | Description |
+------------------+---------+------------+-----------+-------------+
| new-checkout     | active  | all users  | always    | —           |
| beta-dashboard   | active  | 25%        | always    | —           |
| holiday-banner   | active  | all users  | 2026-12-01 → 2026-12-31 | — |
+------------------+---------+------------+-----------+-------------+
```

### Drivers

#### Config Driver

Flags are defined in `config/feature-flags.php`. Changes require a deployment. This is the default driver and ideal for flags that are tied to your release cycle.

#### Database Driver

Flags are stored in the `feature_flags` table and can be toggled at runtime via Artisan commands, the facade, or any database tooling. Run the migration before using this driver.

The migration creates the following columns:

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string (unique) | Flag identifier |
| `description` | string (nullable) | Optional description |
| `active` | boolean | Master on/off switch |
| `rollout_percentage` | tinyint (nullable) | Percentage of users (0–100) |
| `enabled_from` | timestamp (nullable) | Activation start |
| `enabled_until` | timestamp (nullable) | Activation end |
| `created_at` / `updated_at` | timestamps | Standard Laravel timestamps |

### Rollout Logic

Percentage rollout uses a deterministic hash to ensure the same user always receives the same result:

```php
$hash = abs(crc32($featureName . $user->getAuthIdentifier()));
$active = ($hash % 100) < $rolloutPercentage;
```

A user in the 25% bucket for `beta-dashboard` will always be in that bucket — they will not flip between requests, and adding new flags will not affect their bucket for existing flags.

### Scheduling

When `enabled_from` or `enabled_until` are set, the flag is only active during the configured window. Dates are parsed with Carbon, so any format Carbon accepts is valid (e.g. `'2026-12-01'`, `'2026-12-01 09:00:00'`).

## API

### `Feature` Facade

| Method | Description |
|--------|-------------|
| `Feature::rule(string $name, callable $rule): void` | Register a custom rule for a feature |
| `Feature::active(string $name): bool` | Check if a feature is active (global check) |
| `Feature::for(Authenticatable $user): static` | Set a user context for rollout checks |
| `Feature::allFeatures(): array` | Return all defined feature flags |
| `Feature::enable(string $name): void` | Enable a flag at runtime (database driver only) |
| `Feature::disable(string $name): void` | Disable a flag at runtime (database driver only) |

### Blade Directives

| Directive | Description |
|-----------|-------------|
| `@feature('name') ... @endfeature` | Render block when feature is active |
| `@feature('name') ... @else ... @endfeature` | Render alternate block when feature is inactive |
| `@elsefeature('name') ... @endfeature` | Else-if variant for a second feature check |
| `@featurefor('name', $user) ... @endfeaturefor` | Per-user feature check respecting rollout |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
