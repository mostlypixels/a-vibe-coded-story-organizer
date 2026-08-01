<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Appearance & accessibility section of the Admin Configuration area.
 *
 * Thin: returns the section view. This placeholder page is enriched by the
 * `display-configurator` spec (`.specs/draft/display-configurator`), which is
 * a later task, not this one.
 */
class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.appearance.edit');
    }
}
