<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Export & import section of the Admin Configuration area.
 *
 * Thin: returns the section view. Task 03 adds the Export/Import tabs; for now
 * this is a placeholder stub. No backup/restore engine exists yet.
 */
class DataTransferController extends Controller
{
    public function index(): View
    {
        return view('admin.data.index');
    }
}
