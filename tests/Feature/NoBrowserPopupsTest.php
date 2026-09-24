<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Browser pop-ups ignore the theme and fonts. Use `x-confirm-delete-dialog`
 * or `x-dialog` instead.
 */
class NoBrowserPopupsTest extends TestCase
{
    private const PATTERN = '/\b(?:confirm|prompt|alert)\(/';

    public function test_no_view_or_script_opens_a_browser_popup(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (['resources/views', 'resources/js'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                // Tests may name a pop-up to prove that it is not called.
                if (! $file->isFile() || str_ends_with($file->getFilename(), '.test.js')) {
                    continue;
                }

                if (preg_match(self::PATTERN, file_get_contents($file->getPathname()))) {
                    $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
