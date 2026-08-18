<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Prevent hue-specific classes from bypassing the theme tokens. */
class NoHueNamedColorsTest extends TestCase
{
    /** @var list<string> */
    private const SCANNED = ['resources/views', 'resources/js', 'resources/css', 'app'];

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['php', 'js', 'css'];

    /** Match hue shades without rejecting the semantic `neutral` token. */
    private const PATTERN = '/\b(?:ocean|aqua|navy|sun|flame|gray|slate|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|zinc|neutral|stone)-(?:50|[1-9]00|950)\b|\b(?:text|bg)-white\b/';

    public function test_no_file_names_a_hue(): void
    {
        $offenders = [];

        foreach ($this->scannableFiles() as $relativePath => $contents) {
            if (preg_match_all(self::PATTERN, $contents, $matches) > 0) {
                $offenders[$relativePath] = array_values(array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders, $this->explain($offenders));
    }

    /**
     * Every scannable file, keyed by its slash-separated path relative to the
     * project root, with plain-PHP comments removed.
     *
     * @return array<string, string>
     */
    private function scannableFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (self::SCANNED as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), self::EXTENSIONS, true)) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $relativePath = ltrim(substr($path, strlen(str_replace('\\', '/', $root))), '/');

                $files[$relativePath] = $this->readable($file, (string) file_get_contents($path));
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Strip comments from plain PHP so documentation can quote the hue names it
     * is warning about. Blade is excluded: `.blade.php` is not valid PHP for the
     * tokenizer, and its comments render alongside classes we do control.
     */
    private function readable(SplFileInfo $file, string $contents): string
    {
        if ($file->getExtension() !== 'php' || str_ends_with($file->getFilename(), '.blade.php')) {
            return $contents;
        }

        $code = '';

        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @param  array<string, list<string>>  $offenders
     */
    private function explain(array $offenders): string
    {
        $lines = [];

        foreach ($offenders as $path => $matches) {
            $lines[] = "{$path}: ".implode(', ', $matches);
        }

        return 'A colour must be named for its role, not its hue. Replace these with a token from '
            ."App\\Support\\ThemeTokens (bg-surface, text-content-muted, focus:ring-focus, …):\n- "
            .implode("\n- ", $lines);
    }
}
