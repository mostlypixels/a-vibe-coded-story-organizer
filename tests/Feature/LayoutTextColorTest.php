<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Require a default text token on each themed HTML layout. */
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
