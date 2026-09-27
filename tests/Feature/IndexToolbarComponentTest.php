<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Guard the list toolbar's layout rules. */
class IndexToolbarComponentTest extends TestCase
{
    /**
     * A select is as wide as its longest option. Long chapter names made the scenes
     * list 841px wide on a 390px phone (#291).
     */
    public function test_a_filter_select_cannot_widen_the_page(): void
    {
        $rendered = Blade::render(<<<'BLADE'
            <x-index-toolbar sort="position" direction="asc" search-placeholder="Search" clear-url="/x" create-url="/x/create" create-label="New">
                <x-select name="chapter"><option>A very long chapter name</option></x-select>
            </x-index-toolbar>
            BLADE);

        preg_match('/<form method="GET" class="([^"]*)"/', $rendered, $match);
        $classes = explode(' ', $match[1] ?? '');

        $this->assertContains('min-w-0', $classes);
        $this->assertContains('max-w-full', $classes);
        $this->assertContains('[&_select]:max-w-full', $classes);
    }
}
