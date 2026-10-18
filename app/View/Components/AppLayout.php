<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  string|null  $title  The tab title's page part, for a page with no breadcrumb trail.
     * @param  bool  $fullHeight  The page fills the window under the menus at full width, and the body does not scroll. The page scrolls its own regions.
     */
    public function __construct(
        public ?string $title = null,
        public bool $fullHeight = false,
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
