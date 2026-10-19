<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesScene;
use App\Services\SceneReviewContext;
use Illuminate\Console\Command;

/** Prints everything the scene-reviewer agent reads before a review. The text comes from `SceneReviewContext`. */
class SceneReviewContextCommand extends Command
{
    use ResolvesScene;

    protected $signature = 'scene:review-context
        {scene : Scene ID or name}
        {--project= : Only look for the scene name in this project ID}
        {--full=3 : How many earlier scenes to print in full}';

    protected $description = 'Print a scene with its codex, notes and earlier scenes, for a review';

    public function handle(SceneReviewContext $context): int
    {
        $scene = $this->resolveScene($this->argument('scene'), $this->option('project'));

        if ($scene === null) {
            return self::FAILURE;
        }

        foreach ($context->lines($scene, (int) $this->option('full')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
