<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Setup;

use CarthageSoftware\ToolChainBenchmarks\Configuration\Project;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\Filesystem;
use Psl\Shell;
use Psl\Str;

/**
 * Handles cloning and updating project repositories for benchmarking.
 */
final readonly class ProjectCloner
{
    /**
     * Clone a project repository or update it if it already exists.
     *
     * @param non-empty-string $ws
     */
    public static function cloneOrUpdate(Project $project, string $ws): bool
    {
        if (Filesystem\exists($ws . '/.git')) {
            Output::info('Repository already cloned, updating...');
            try {
                Shell\execute('git', ['fetch', 'origin'], $ws);
                Shell\execute('git', ['checkout', $project->getRef()], $ws);
                Shell\execute('git', ['pull', 'origin', $project->getRef()], $ws);
            } catch (Shell\Exception\FailedExecutionException) {
                Output::warn('Git pull failed, continuing with existing checkout');
            }
            return true;
        }

        Output::info(Str\format('Cloning %s (ref: %s)...', $project->getRepo(), $project->getRef()));
        try {
            Shell\execute('git', [
                'clone',
                '--branch',
                $project->getRef(),
                '--depth',
                '1',
                $project->getRepo(),
                $ws,
            ]);
            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Git clone failed: %s', $e->getErrorOutput()));
            return false;
        }
    }

    /**
     * Run the project's setup command.
     *
     * @param non-empty-string $ws
     */
    public static function runSetup(Project $project, string $ws): bool
    {
        Output::info('Running project setup...');
        try {
            Shell\execute('sh', ['-c', $project->getSetupCommand()], $ws);
            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Project setup failed: %s', $e->getErrorOutput()));
            return false;
        }
    }
}
