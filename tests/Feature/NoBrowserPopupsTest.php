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

    public function test_no_view_opens_a_browser_popup(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && preg_match(self::PATTERN, file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        $this->assertSame([], $offenders);
    }
}
