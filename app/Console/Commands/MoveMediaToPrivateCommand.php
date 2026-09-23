<?php

namespace App\Console\Commands;

use App\Services\CodexMediaService;
use App\Services\CoverImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves covers and codex media from the `public` disk to the private `media` disk.
 *
 * Older installs stored these files where the web server sent them without a
 * sign-in. The stored paths stay the same on the new disk, so no database row
 * changes. The command is safe to run again: it skips a file that is already moved.
 */
class MoveMediaToPrivateCommand extends Command
{
    protected $signature = 'media:move-to-private';

    protected $description = 'Move covers and codex media from the public disk to the private media disk';

    /** @var list<string> */
    private const DIRECTORIES = [
        CoverImageService::PROJECT_COVER_DIRECTORY,
        CoverImageService::BOOK_COVER_DIRECTORY,
        CoverImageService::CHAPTER_COVER_DIRECTORY,
        CodexMediaService::DIRECTORY,
    ];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $media = Storage::disk(CodexMediaService::DISK);
        $moved = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                if (! $media->exists($path)) {
                    $media->writeStream($path, $public->readStream($path));
                }

                $public->delete($path);
                $moved++;
            }
        }

        $this->info("Moved {$moved} file(s) to the private media disk.");

        return self::SUCCESS;
    }
}
