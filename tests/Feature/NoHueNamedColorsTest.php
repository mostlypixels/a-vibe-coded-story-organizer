<?php

namespace Tests\Feature;

use App\Support\SearchSnippet;
use App\Support\ThemeTokens;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A class may name what a colour is *for*, never which hue it happens to be.
 *
 * `bg-ocean-600` says a thing is blue. It does not say where it may be used, so
 * it becomes a lie the moment a theme flips — and the whole point of the theme
 * switcher is that a preset can flip. `bg-primary` cannot lie: the preset owns
 * the value. The vocabulary lives in {@see ThemeTokens}.
 *
 * The sweep is complete: every scanned file was checked against an allow-list of
 * still-to-sweep paths while the ~900-usage rename was in flight, and that
 * mechanism is gone now the list is empty. Do not re-add it for a one-off
 * exception — fix the offending file instead.
 *
 * ## What is scanned
 *
 * The five hand-authored ramps (`ocean`, `aqua`, `navy`, `sun`, `flame`), every
 * built-in Tailwind hue a template might reach for instead (`gray`, `slate`, `red`,
 * `blue`, `emerald`, `indigo`, …), and the two literal colours that have role tokens
 * (`text-white`, `bg-white`).
 *
 * Comments are stripped from plain PHP files before scanning: `ThemeTokens` and
 * `theme:ramp` both have to *say* `bg-ocean-600` to explain themselves, and a
 * documentation sentence is not a painted pixel. Blade, CSS and JS are scanned
 * whole — their comments are ours to keep in step.
 *
 * Plain PHPUnit\Framework\TestCase, not Tests\TestCase: this reads files off disk
 * and boots nothing.
 */
class NoHueNamedColorsTest extends TestCase
{
    /**
     * Directories scanned, relative to the project root.
     *
     * `app/` is in the list because Blade is not the only thing that writes a
     * class name — {@see SearchSnippet} builds the search `<mark>`
     * itself, and a Blade-only sweep would leave it dangling.
     *
     * @var list<string>
     */
    private const SCANNED = ['resources/views', 'resources/js', 'resources/css', 'app'];

    /**
     * @var list<string>
     */
    private const EXTENSIONS = ['php', 'js', 'css'];

    /**
     * A ramp reference (`bg-ocean-600`, `--color-gray-200`, `divide-gray-200`), a
     * built-in Tailwind hue (`bg-red-600`, `border-emerald-300`) or one of the two
     * literals that have role tokens.
     *
     * Widened in task 10 to cover every built-in Tailwind hue, not just the five
     * hand-authored ramps and the two neutral ones — status colors (`red`, `green`,
     * `blue`, `yellow`, `amber`, …) previously passed straight through, ungated,
     * exactly where the sweep is subtlest. The shade-digit suffix stays mandatory:
     * `neutral` is both a Tailwind ramp and one of our own token names (`bg-neutral`),
     * and requiring `-50`/`-600`/etc. is what tells them apart — `bg-neutral` (no
     * digit) is our token and passes; `bg-neutral-600` (Tailwind's own ramp) is a hue
     * reference and fails.
     */
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
