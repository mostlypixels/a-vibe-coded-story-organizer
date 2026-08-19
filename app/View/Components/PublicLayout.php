<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** The layout for public pages that must not appear in search results. */
class PublicLayout extends Component
{
    public function render(): View
    {
        return view('layouts.public');
    }
}
