<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Facades;

use Illuminate\Support\Facades\Facade;
use PhilipRehberger\FeatureFlags\FeatureManager;
use PhilipRehberger\FeatureFlags\PendingFeatureCheck;

/**
 * @method static bool active(string $feature)
 * @method static PendingFeatureCheck for(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static array<string, array<string, mixed>> allFeatures()
 * @method static void enable(string $feature)
 * @method static void disable(string $feature)
 *
 * @see \PhilipRehberger\FeatureFlags\FeatureManager
 */
class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureManager::class;
    }
}
