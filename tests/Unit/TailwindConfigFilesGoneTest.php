<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Keep Tailwind configuration in the CSS source only. */
class TailwindConfigFilesGoneTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_tailwind_config_js_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            $this->repoRoot().'/tailwind.config.js',
            'tailwind.config.js has reappeared. Tailwind 4 reads its theme from the `@theme` '
            .'block in resources/css/app.css; a JS config file reintroduces a second, unread '
            .'source of truth for the theme and must be removed again.'
        );
    }

    public function test_postcss_config_js_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            $this->repoRoot().'/postcss.config.js',
            'postcss.config.js has reappeared. The PostCSS pipeline was replaced by the '
            .'@tailwindcss/vite plugin (see vite.config.js); a PostCSS config file '
            .'reintroduces a second, unused build pipeline and must be removed again.'
        );
    }
}
