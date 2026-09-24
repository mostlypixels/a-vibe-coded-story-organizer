<?php

namespace Tests\Feature;

use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Keyboard and screen-reader users need a name and a state on each control. */
class AccessibleControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_link_on_a_page_skips_to_the_main_content(): void
    {
        $user = User::factory()->create();
        [$project] = $this->projectWithBook($user);

        $html = $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->getContent();

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $firstLink = $document->querySelector('body a');

        $this->assertSame('#main-content', $firstLink->getAttribute('href'));
        $this->assertNotNull($document->querySelector('main#main-content'));
    }

    public function test_the_logo_link_and_the_list_search_have_a_name(): void
    {
        $user = User::factory()->create();
        [, $book] = $this->projectWithBook($user);

        $html = $this->actingAs($user)->get(route('books.scenes.index', $book))->assertOk()->getContent();
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $this->assertSame('Dashboard', $document->querySelector('nav a:has(svg)')->getAttribute('aria-label'));
        $this->assertSame('Search by name...', $document->querySelector('input[name=search]')->getAttribute('aria-label'));
    }

    public function test_the_tag_and_event_picker_inputs_have_a_name(): void
    {
        $tags = HTMLDocument::createFromString(Blade::render('<x-tag-picker :tags="[]" />'), LIBXML_NOERROR);
        $events = HTMLDocument::createFromString(Blade::render('<x-event-picker name="mentioned_events" :events="[]" />'), LIBXML_NOERROR);

        $this->assertSame('Tags', $tags->querySelector('input[type=text]')->getAttribute('aria-label'));
        $this->assertSame('Mentions events', $events->querySelector('input[type=text]')->getAttribute('aria-label'));
    }

    public function test_a_wide_table_scrolls_sideways_instead_of_cutting_off_columns(): void
    {
        $wrapper = HTMLDocument::createFromString(Blade::render('<x-table><tr><td>A</td></tr></x-table>'), LIBXML_NOERROR)
            ->querySelector('div');

        $classes = explode(' ', $wrapper->getAttribute('class'));

        $this->assertContains('overflow-x-auto', $classes);
        // Absolute children such as sr-only text escape a scroller that is not positioned.
        $this->assertContains('relative', $classes);
        $this->assertNotContains('overflow-hidden', $classes);
    }

    public function test_a_toolbar_toggle_announces_its_state_but_a_menu_trigger_does_not(): void
    {
        $toggle = HTMLDocument::createFromString(
            Blade::render('<x-wysiwyg.toolbar-button command="toggleBold" :active="[\'bold\']" title="Bold">B</x-wysiwyg.toolbar-button>'),
            LIBXML_NOERROR,
        )->querySelector('button');

        $trigger = HTMLDocument::createFromString(
            Blade::render('<x-wysiwyg.toolbar-button active-expression="isOn(\'heading\')" :dropdown="true" title="Style">S</x-wysiwyg.toolbar-button>'),
            LIBXML_NOERROR,
        )->querySelector('button');

        $this->assertSame('false', $toggle->getAttribute('aria-pressed'));
        $this->assertStringContainsString("isOn('bold')", $toggle->getAttribute(':aria-pressed'));
        $this->assertFalse($trigger->hasAttribute('aria-pressed'));
    }
}
