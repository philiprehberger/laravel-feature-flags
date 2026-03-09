<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface FeatureDriver
{
    /**
     * Determine if the given feature is globally active.
     */
    public function isActive(string $feature): bool;

    /**
     * Determine if the given feature is active for a specific user,
     * taking percentage rollout and scheduling into account.
     */
    public function isActiveForUser(string $feature, Authenticatable $user): bool;

    /**
     * Return all defined feature flags as an associative array.
     * Each entry should contain at minimum: name, active (bool), rollout_percentage (int|null).
     *
     * @return array<string, array<string, mixed>>
     */
    public function allFeatures(): array;

    /**
     * Enable a feature at runtime.
     *
     * @throws \LogicException if the driver does not support runtime toggling
     */
    public function enable(string $feature): void;

    /**
     * Disable a feature at runtime.
     *
     * @throws \LogicException if the driver does not support runtime toggling
     */
    public function disable(string $feature): void;
}
