<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags;

use Illuminate\Contracts\Auth\Authenticatable;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;

class FeatureManager
{
    /** @var array<string, callable> */
    private array $rules = [];

    public function __construct(protected FeatureDriver $driver) {}

    /**
     * Register a custom rule for a feature.
     *
     * The callable receives the user (or null for global checks) and must return a bool.
     *
     * @param  callable(Authenticatable|null): bool  $rule
     */
    public function rule(string $feature, callable $rule): void
    {
        $this->rules[$feature] = $rule;
    }

    /**
     * Determine if the given feature is globally active.
     * For percentage rollout features this returns true only when no
     * rollout is configured — use for(user)->active() for per-user checks.
     */
    public function active(string $feature): bool
    {
        if (! $this->driver->isActive($feature)) {
            return false;
        }

        if (isset($this->rules[$feature])) {
            return ($this->rules[$feature])(null);
        }

        return true;
    }

    /**
     * Begin a user-targeted feature check.
     */
    public function for(Authenticatable $user): PendingFeatureCheck
    {
        return new PendingFeatureCheck($this->driver, $user, $this->rules);
    }

    /**
     * Return all defined feature flags.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allFeatures(): array
    {
        return $this->driver->allFeatures();
    }

    /**
     * Enable a feature at runtime.
     * Only supported by the database driver.
     */
    public function enable(string $feature): void
    {
        $this->driver->enable($feature);
    }

    /**
     * Disable a feature at runtime.
     * Only supported by the database driver.
     */
    public function disable(string $feature): void
    {
        $this->driver->disable($feature);
    }
}
