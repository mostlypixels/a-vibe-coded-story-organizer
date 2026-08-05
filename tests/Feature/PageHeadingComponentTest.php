<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `<x-page-heading>` — the document heading every converted page gets above
 * its content column now that the header band's title is gone (breadcrumbs
 * are a `<nav>`, not a heading). See {@see BreadcrumbsComponentTest} for why
 * this is exercised standalone before any page actually uses it.
 */
class PageHeadingComponentTest extends TestCase
{
    private function render(string $template, array $data = []): string
    {
        $rendered = Blade::render($template, $data);

        $this->assertStringNotContainsString('<x-', $rendered);

        return $rendered;
    }

    public function test_renders_an_h1_by_default(): void
    {
        $rendered = $this->render('<x-page-heading>Story Overview</x-page-heading>');

        $this->assertStringContainsString('<h1', $rendered);
        $this->assertStringContainsString('Story Overview', $rendered);
    }

    public function test_level_can_be_overridden(): void
    {
        $rendered = $this->render('<x-page-heading :level="2">Acts</x-page-heading>');

        $this->assertStringContainsString('<h2', $rendered);
        $this->assertStringNotContainsString('<h1', $rendered);
    }
}
