<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Drivers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionInterface;
use PhilipRehberger\FeatureFlags\Contracts\FeatureDriver;
use stdClass;

class DatabaseDriver implements FeatureDriver
{
    public function __construct(protected ConnectionInterface $db) {}

    public function isActive(string $feature): bool
    {
        $record = $this->find($feature);

        if ($record === null) {
            return false;
        }

        if (! (bool) $record->active) {
            return false;
        }

        return $this->withinSchedule($record);
    }

    public function isActiveForUser(string $feature, Authenticatable $user): bool
    {
        $record = $this->find($feature);

        if ($record === null) {
            return false;
        }

        if (! (bool) $record->active) {
            return false;
        }

        if (! $this->withinSchedule($record)) {
            return false;
        }

        $rollout = $record->rollout_percentage !== null ? (int) $record->rollout_percentage : null;

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
        $rows = $this->db->table('feature_flags')->get();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->name] = [
                'name' => $row->name,
                'description' => $row->description,
                'active' => (bool) $row->active,
                'rollout_percentage' => $row->rollout_percentage !== null ? (int) $row->rollout_percentage : null,
                'enabled_from' => $row->enabled_from,
                'enabled_until' => $row->enabled_until,
            ];
        }

        return $result;
    }

    public function enable(string $feature): void
    {
        $this->db->table('feature_flags')->updateOrInsert(
            ['name' => $feature],
            ['active' => true, 'updated_at' => Carbon::now()]
        );
    }

    public function disable(string $feature): void
    {
        $this->db->table('feature_flags')->updateOrInsert(
            ['name' => $feature],
            ['active' => false, 'updated_at' => Carbon::now()]
        );
    }

    private function find(string $feature): ?stdClass
    {
        $record = $this->db->table('feature_flags')->where('name', $feature)->first();

        return $record instanceof stdClass ? $record : null;
    }

    private function withinSchedule(stdClass $record): bool
    {
        $now = Carbon::now();

        if ($record->enabled_from !== null) {
            $from = Carbon::parse($record->enabled_from);
            if ($now->lt($from)) {
                return false;
            }
        }

        if ($record->enabled_until !== null) {
            $until = Carbon::parse($record->enabled_until);
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
