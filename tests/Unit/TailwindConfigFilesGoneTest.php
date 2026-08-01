<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Tailwind 4 migration (tailwind-4 task 07). Tailwind 4 reads its theme from
 * `@theme` in `resources/css/app.css`; `tailwind.config.js` and `postcss.config.js` were
 * deleted (task 01) precisely so there is one source of truth for the theme, one the
 * theme-switcher spec builds runtime overrides on top of. Either file reappearing — a
 * future `npx tailwindcss init`, a merge that resurrects an old branch — would silently
 * bring back a second, unread source of truth for the theme.
 *
 * Plain filesystem assertions, no database — a Unit test that runs under `composer test`
 * with no extra wiring, in the spirit of `tests/Unit/SpecsStatusConsistencyTest`.
 */
class TailwindConfigFilesGoneTest extends TestCase
{
    private function repoRoot(): string
    {
        // tests/Unit → repo root is two levels up.
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
