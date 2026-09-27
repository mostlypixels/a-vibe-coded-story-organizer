<?php

namespace Tests\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * A fake disk whose first folder deletes stop after one file and return false.
 * This is what a Docker bind mount under Windows does to a large folder.
 */
class PartialDeleteDisk extends FilesystemAdapter
{
    /** Folder deletes left that fail part of the way. */
    public int $partialDeletes;

    /** Replaces the faked $name disk with one that fails $partialDeletes times. */
    public static function install(string $name, int $partialDeletes = 1): self
    {
        $fake = Storage::disk($name);
        $disk = new self($fake->getDriver(), $fake->getAdapter(), $fake->getConfig());
        $disk->partialDeletes = $partialDeletes;
        Storage::set($name, $disk);

        return $disk;
    }

    public function deleteDirectory($directory)
    {
        if ($this->partialDeletes > 0) {
            $this->partialDeletes--;
            $this->delete($this->allFiles($directory)[0] ?? '');

            return false;
        }

        return parent::deleteDirectory($directory);
    }
}
