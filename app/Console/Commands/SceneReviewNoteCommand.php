<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesScene;
use App\Services\SceneReviewNoteWriter;
use Illuminate\Console\Command;

/**
 * Saves the scene-reviewer agent's findings. The body is HTML, read from a file or stdin,
 * because a review has lists and headings that do not fit in an argument.
 */
class SceneReviewNoteCommand extends Command
{
    use ResolvesScene;

    protected $signature = 'scene:review-note
        {scene : Scene ID or name}
        {--project= : Only look for the scene name in this project ID}
        {--file= : Read the HTML body from this file instead of stdin}';

    protected $description = 'Create or replace the review note of a scene, in the TODO note category';

    public function handle(SceneReviewNoteWriter $writer): int
    {
        $scene = $this->resolveScene($this->argument('scene'), $this->option('project'));

        if ($scene === null) {
            return self::FAILURE;
        }

        $file = $this->option('file');

        if ($file !== null && ! is_readable($file)) {
            $this->error("Cannot read {$file}.");

            return self::FAILURE;
        }

        $body = trim((string) ($file !== null ? file_get_contents($file) : stream_get_contents(STDIN)));

        if ($body === '') {
            $this->error('The note body is empty.');

            return self::FAILURE;
        }

        $note = $writer->write($scene, $body);

        $this->info("Saved note {$note->id}: ".route('notes.show', $note, absolute: false));

        return self::SUCCESS;
    }
}
