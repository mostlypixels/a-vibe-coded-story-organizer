<?php

namespace Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DropdownComponentTest extends TestCase
{
    public function test_a_default_dropdown_toggles_on_click_and_ignores_hover(): void
    {
        $root = $this->root(Blade::render(<<<'BLADE'
            <x-dropdown>
                <x-slot name="trigger"><x-disclosure-button>Menu</x-disclosure-button></x-slot>
                <x-slot name="content"><a href="/x">Item</a></x-slot>
            </x-dropdown>
            BLADE));

        $this->assertStringContainsString('hover: false', $root->getAttribute('x-data'));
        $this->assertFalse($root->hasAttribute('data-hover'));
        $this->assertFalse($root->hasAttribute('@pointerenter'));
        $this->assertFalse($root->hasAttribute('@pointerleave'));
        $this->assertSame('triggerClick($event)', $root->firstElementChild->getAttribute('@click'));
    }

    public function test_a_hover_dropdown_listens_to_the_pointer(): void
    {
        $root = $this->root(Blade::render(<<<'BLADE'
            <x-dropdown hover>
                <x-slot name="trigger"><a href="/story">Story</a><x-disclosure-button>Story menu</x-disclosure-button></x-slot>
                <x-slot name="content"><a href="/x">Item</a></x-slot>
            </x-dropdown>
            BLADE));

        $this->assertStringContainsString('hover: true', $root->getAttribute('x-data'));
        $this->assertTrue($root->hasAttribute('data-hover'));
        $this->assertSame('pointerEnter($event)', $root->getAttribute('@pointerenter'));
        $this->assertSame('pointerLeave($event)', $root->getAttribute('@pointerleave'));
    }

    public function test_every_dropdown_closes_on_escape_and_when_another_opens(): void
    {
        $root = $this->root(Blade::render(<<<'BLADE'
            <x-dropdown>
                <x-slot name="trigger"><x-disclosure-button>Menu</x-disclosure-button></x-slot>
                <x-slot name="content"><a href="/x">Item</a></x-slot>
            </x-dropdown>
            BLADE));

        $this->assertSame('escape($event)', $root->getAttribute('@keydown.escape'));
        $this->assertSame('escapeOutside()', $root->getAttribute('@keydown.escape.window'));
        $this->assertSame('otherOpened($event)', $root->getAttribute('@dropdown-opened.window'));
    }

    private function root(string $html): Element
    {
        $root = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('[x-data]');
        $this->assertNotNull($root);

        return $root;
    }
}
