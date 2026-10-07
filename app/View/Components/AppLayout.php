<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  string|null  $title  The tab title's page part, for a page with no breadcrumb trail.
     */
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
