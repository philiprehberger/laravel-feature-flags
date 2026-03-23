<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags;

use Illuminate\Contracts\Auth\Authenticatable;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;

class PendingFeatureCheck
{
    /**
     * @param  array<string, callable>  $rules
     */
    public function __construct(
        protected FeatureDriver $driver,
        protected Authenticatable $user,
        protected array $rules = [],
    ) {}

    /**
     * Determine if the given feature is active for the bound user,
     * respecting percentage rollout, scheduling constraints, and custom rules.
     */
    public function active(string $feature): bool
    {
        if (! $this->driver->isActiveForUser($feature, $this->user)) {
            return false;
        }

        if (isset($this->rules[$feature])) {
            return ($this->rules[$feature])($this->user);
        }

        return true;
    }
}
