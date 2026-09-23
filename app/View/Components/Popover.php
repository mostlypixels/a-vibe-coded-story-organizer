<?php

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * A panel of help text that opens next to its trigger button.
 *
 * Put an `x-disclosure-button` in the `trigger` slot. The button reads
 * {@see $disclosureId} through `@aware` and points `aria-controls` at the panel.
 */
class Popover extends Component
{
    public readonly string $disclosureId;

    public readonly string $positionClasses;

    public function __construct(
        public readonly ?string $title = null,
        string $position = 'bottom',
        public readonly string $width = 'w-64',
    ) {
        $this->disclosureId = 'popover-'.Str::lower(Str::random(8));

        $this->positionClasses = match ($position) {
            'top' => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
            'left' => 'right-full top-1/2 -translate-y-1/2 mr-2',
            'right' => 'left-full top-1/2 -translate-y-1/2 ml-2',
            default => 'top-full left-1/2 -translate-x-1/2 mt-2',
        };
    }

    public function render(): View
    {
        return view('components.popover');
    }
}
