<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Appearance & accessibility section of the Admin Configuration area.
 *
 * Thin: returns the section view. This placeholder page is the final v1 form —
 * no later task enriches it.
 */
class AppearanceController extends Controller
{
    public function edit(): View
    {
        return view('admin.appearance.edit');
    }
}
