<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Export & import section of the Admin Configuration area.
 *
 * Thin: returns the section view. The Export tab posts to ExportController; the
 * Import tab is a future spec. Provides the signed-in user's projects (ordered by
 * name) so the Export form can offer them in its selector — the same access
 * pattern the Dashboard uses.
 */
class DataTransferController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('admin.data.index', ['projects' => $projects]);
    }
}
