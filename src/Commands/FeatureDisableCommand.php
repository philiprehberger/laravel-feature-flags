<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Commands;

use Illuminate\Console\Command;
use PhilipRehberger\FeatureFlags\FeatureManager;

class FeatureDisableCommand extends Command
{
    protected $signature = 'feature:disable {name : The name of the feature flag to disable}';

    protected $description = 'Disable a feature flag (database driver only)';

    public function handle(FeatureManager $manager): int
    {
        $name = $this->argument('name');

        try {
            $manager->disable($name);
            $this->components->info("Feature [{$name}] has been disabled.");
        } catch (\LogicException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
