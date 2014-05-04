<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags;

use Illuminate\Contracts\Auth\Authenticatable;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;

class PendingFeatureCheck
{
    public function __construct(
        protected FeatureDriver $driver,
        protected Authenticatable $user,
    ) {}

    /**
     * Determine if the given feature is active for the bound user,
     * respecting percentage rollout and scheduling constraints.
     */
    public function active(string $feature): bool
    {
        return $this->driver->isActiveForUser($feature, $this->user);
    }
}
