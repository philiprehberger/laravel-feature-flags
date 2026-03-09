<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Commands;

use Illuminate\Console\Command;
use PhilipRehberger\FeatureFlags\FeatureManager;

class FeatureEnableCommand extends Command
{
    protected $signature = 'feature:enable {name : The name of the feature flag to enable}';

    protected $description = 'Enable a feature flag (database driver only)';

    public function handle(FeatureManager $manager): int
    {
        $name = $this->argument('name');

        try {
            $manager->enable($name);
            $this->components->info("Feature [{$name}] has been enabled.");
        } catch (\LogicException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
