<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Commands;

use Illuminate\Console\Command;
use PhilipRehberger\FeatureFlags\FeatureManager;

class FeatureListCommand extends Command
{
    protected $signature = 'feature:list';

    protected $description = 'List all defined feature flags with their current status';

    public function handle(FeatureManager $manager): int
    {
        $features = $manager->allFeatures();

        if (empty($features)) {
            $this->components->info('No feature flags defined.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($features as $name => $config) {
            $status = $config['active'] ? '<fg=green>active</>' : '<fg=red>inactive</>';
            $rollout = $config['rollout_percentage'] !== null
                ? $config['rollout_percentage'].'%'
                : 'all users';

            $schedule = 'always';
            if ($config['enabled_from'] !== null || $config['enabled_until'] !== null) {
                $from = $config['enabled_from'] ?? '—';
                $until = $config['enabled_until'] ?? '—';
                $schedule = "{$from} → {$until}";
            }

            $rows[] = [
                $name,
                $status,
                $rollout,
                $schedule,
                $config['description'] ?? '—',
            ];
        }

        $this->table(
            ['Name', 'Status', 'Rollout', 'Schedule', 'Description'],
            $rows,
        );

        return self::SUCCESS;
    }
}
