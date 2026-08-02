<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every themed layout's <body> must name a default text colour.
 *
 * Without one the body falls back to the browser's black. That is invisible in
 * Daylight — black on white is what a reader expects — and it stays invisible to
 * every test we have: the contrast matrix only measures pairs the vocabulary
 * declares, and a UA default is not a token. Under a dark preset it is black text
 * on a dark surface.
 *
 * The bug this guards against is not the layout itself but everything below it: a
 * component that forgets to name a colour inherits whatever the body set, so the
 * body is the one place that decides whether "forgot" means "themed anyway" or
 * "unreadable". `.revision-diff` is the one that surfaced it, after two layouts had
 * already shipped without the class.
 *
 * The EPUB and print-book layouts are deliberately excluded — they render to a file
 * with its own stylesheet and never see a preset.
 *
 * Plain PHPUnit\Framework\TestCase, not Tests\TestCase: this reads files off disk
 * and boots nothing.
 */
class LayoutTextColorTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function themedLayouts(): array
    {
        return [
            'app' => ['resources/views/layouts/app.blade.php'],
            'guest' => ['resources/views/layouts/guest.blade.php'],
            'public' => ['resources/views/layouts/public.blade.php'],
            'welcome' => ['resources/views/welcome.blade.php'],
        ];
    }

    #[DataProvider('themedLayouts')]
    public function test_the_body_names_a_default_text_colour(string $relativePath): void
    {
        $path = dirname(__DIR__, 2).'/'.$relativePath;

        $this->assertFileExists($path);

        $markup = file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/<body[^>]*\sclass="[^"]*\btext-content\b/',
            $markup,
            "{$relativePath}'s <body> must set `text-content`, or everything that does not "
            .'name its own colour falls back to the browser black and disappears under a dark preset.',
        );
    }
}
