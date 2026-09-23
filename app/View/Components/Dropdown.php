<?php

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * A menu that opens under its trigger button.
 *
 * Put an `x-disclosure-button` in the `trigger` slot. The button reads
 * {@see $disclosureId} through `@aware` and points `aria-controls` at the menu.
 * The id must be public: `@aware` sees only public component data.
 */
class Dropdown extends Component
{
    public readonly string $disclosureId;

    public readonly string $alignmentClasses;

    public readonly string $widthClass;

    public function __construct(
        string $align = 'right',
        string $width = '48',
        public readonly string $contentClasses = 'py-1 bg-surface-overlay',
        public readonly string $offsetClasses = 'mt-2',
    ) {
        // A page can show the same menu more than one time, for example one per editor.
        $this->disclosureId = 'dropdown-'.Str::lower(Str::random(8));

        $this->alignmentClasses = match ($align) {
            'left' => 'ltr:origin-top-left rtl:origin-top-right inset-s-0',
            'top' => 'origin-top',
            default => 'ltr:origin-top-right rtl:origin-top-left inset-e-0',
        };

        $this->widthClass = $width === '48' ? 'w-48' : $width;
    }

    public function render(): View
    {
        return view('components.dropdown');
    }
}
