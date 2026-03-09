<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Drivers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;

class ConfigDriver implements FeatureDriver
{
    /**
     * @param  array<string, mixed>  $features
     */
    public function __construct(protected array $features) {}

    public function isActive(string $feature): bool
    {
        $config = $this->resolve($feature);

        if ($config === null) {
            return false;
        }

        if (! $config['active']) {
            return false;
        }

        return $this->withinSchedule($config);
    }

    public function isActiveForUser(string $feature, Authenticatable $user): bool
    {
        $config = $this->resolve($feature);

        if ($config === null) {
            return false;
        }

        if (! $config['active']) {
            return false;
        }

        if (! $this->withinSchedule($config)) {
            return false;
        }

        $rollout = $config['rollout_percentage'];

        if ($rollout === null) {
            return true;
        }

        return $this->withinRollout($feature, $user, $rollout);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allFeatures(): array
    {
        $result = [];

        foreach ($this->features as $name => $definition) {
            $config = $this->normalise($name, $definition);
            $result[$name] = [
                'name' => $name,
                'active' => $config['active'],
                'rollout_percentage' => $config['rollout_percentage'],
                'enabled_from' => $config['enabled_from'],
                'enabled_until' => $config['enabled_until'],
                'description' => null,
            ];
        }

        return $result;
    }

    public function enable(string $feature): void
    {
        throw new LogicException('The config driver does not support runtime toggling. Use the database driver instead.');
    }

    public function disable(string $feature): void
    {
        throw new LogicException('The config driver does not support runtime toggling. Use the database driver instead.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve(string $feature): ?array
    {
        if (! array_key_exists($feature, $this->features)) {
            return null;
        }

        return $this->normalise($feature, $this->features[$feature]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalise(string $name, mixed $definition): array
    {
        if (is_bool($definition)) {
            return [
                'active' => $definition,
                'rollout_percentage' => null,
                'enabled_from' => null,
                'enabled_until' => null,
            ];
        }

        if (is_array($definition)) {
            return [
                'active' => (bool) ($definition['active'] ?? false),
                'rollout_percentage' => isset($definition['rollout']) ? (int) $definition['rollout'] : null,
                'enabled_from' => $definition['enabled_from'] ?? null,
                'enabled_until' => $definition['enabled_until'] ?? null,
            ];
        }

        return [
            'active' => false,
            'rollout_percentage' => null,
            'enabled_from' => null,
            'enabled_until' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function withinSchedule(array $config): bool
    {
        $now = Carbon::now();

        if ($config['enabled_from'] !== null) {
            $from = Carbon::parse($config['enabled_from']);
            if ($now->lt($from)) {
                return false;
            }
        }

        if ($config['enabled_until'] !== null) {
            $until = Carbon::parse($config['enabled_until']);
            if ($now->gt($until)) {
                return false;
            }
        }

        return true;
    }

    private function withinRollout(string $feature, Authenticatable $user, int $percentage): bool
    {
        $hash = abs(crc32($feature.$user->getAuthIdentifier()));

        return ($hash % 100) < $percentage;
    }
}
