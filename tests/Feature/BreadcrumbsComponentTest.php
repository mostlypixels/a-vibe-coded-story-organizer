<?php

namespace Tests\Feature;

use App\Support\Crumb;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-breadcrumbs>` rendered standalone over a fixture Crumb[], in the manner
 * of {@see WordCountComponentTest}. A fixture reaches trail shapes a real route
 * cannot produce — {@see BreadcrumbsTest} drives the band through a dispatched
 * request instead.
 *
 * Guards the W3C breadcrumb pattern this component implements: one labelled
 * landmark, one current crumb, separators a screen reader never hears, and links
 * only where a crumb has somewhere to go.
 */
class BreadcrumbsComponentTest extends TestCase
{
    /**
     * Render a template, asserting the component tag actually compiled — see
     * {@see IconButtonComponentTest::render()} for why an uncompiled tag would let every
     * other assertion here pass against literal source text.
     */
    private function render(string $template, array $data = []): string
    {
        $rendered = Blade::render($template, $data);

        $this->assertStringNotContainsString(
            '<x-',
            $rendered,
            'A component tag was emitted as literal text instead of being compiled. '
            .'The usual cause is a Blade directive (@disabled, @if) used as an attribute '
            .'inside an <x-…> tag — use a bound attribute (:disabled="…") instead.',
        );

        return $rendered;
    }

    /**
     * @return list<Crumb>
     */
    private function fixture(): array
    {
        return [
            new Crumb('Dashboard', '/projects/1'),
            new Crumb('Story', null),
            new Crumb('Acts', '/projects/1/acts'),
            new Crumb('Edit Act — The Rising Storm', null, current: true),
        ];
    }

    public function test_renders_exactly_one_breadcrumb_landmark(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => $this->fixture()]);

        $this->assertSame(1, substr_count($rendered, 'aria-label="Breadcrumb"'));
        $this->assertStringContainsString('<nav aria-label="Breadcrumb"', $rendered);
    }

    public function test_exactly_one_crumb_is_marked_current(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => $this->fixture()]);

        $this->assertSame(1, substr_count($rendered, 'aria-current="page"'));
    }

    public function test_separators_are_aria_hidden_and_never_a_list_item(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => $this->fixture()]);

        // Three separators between four crumbs.
        $this->assertSame(3, substr_count($rendered, 'aria-hidden="true"'));
        $this->assertSame(4, substr_count($rendered, '<li'));
    }

    public function test_only_non_current_crumbs_with_a_url_render_as_links(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => $this->fixture()]);

        // Dashboard and Acts have URLs; Story (section trigger) and the
        // current leaf never do.
        $this->assertSame(2, substr_count($rendered, '<a '));
        $this->assertStringContainsString('href="/projects/1"', $rendered);
        $this->assertStringContainsString('href="/projects/1/acts"', $rendered);
        $this->assertStringNotContainsString('<a href="">', $rendered);
    }

    public function test_labels_of_every_crumb_are_present(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => $this->fixture()]);

        $this->assertStringContainsString('Dashboard', $rendered);
        $this->assertStringContainsString('Story', $rendered);
        $this->assertStringContainsString('Acts', $rendered);
        $this->assertStringContainsString('Edit Act — The Rising Storm', $rendered);
    }

    public function test_renders_over_an_empty_list_without_error(): void
    {
        $rendered = $this->render('<x-breadcrumbs :items="$items" />', ['items' => []]);

        $this->assertStringContainsString('<nav aria-label="Breadcrumb"', $rendered);
        $this->assertStringNotContainsString('<li', $rendered);
    }
}
