<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two-way redirect every entity `update()` ends with: the edit form carries
 * both **Save** and **Save and stay** (see `components/edit-actions.blade.php`),
 * and only the latter posts `stay=1`.
 *
 * Shared for the `->with('status', 'saved')` flash rather than for the three
 * lines: that flash is what renders the "Saved" confirmation on the edit page a
 * "Save and stay" returns to, and it is invisible from the call site whether a
 * given controller remembered it. Centralising it means the next entity cannot
 * be the one that does not.
 */
trait RedirectsAfterSave
{
    /**
     * Redirect to the edit form (on "Save and stay") or back to the index.
     *
     * Both targets are `[route name, parameters]` pairs, spread into `route()` —
     * so a single-model route passes the model, and a multi-segment one passes
     * its usual array.
     *
     * @param  array{0: string, 1?: mixed}  $stayTarget  Where "Save and stay" lands.
     * @param  array{0: string, 1?: mixed}  $doneTarget  Where a plain "Save" lands.
     */
    protected function redirectAfterSave(Request $request, array $stayTarget, array $doneTarget): RedirectResponse
    {
        return $request->boolean('stay')
            ? redirect()->route(...$stayTarget)->with('status', 'saved')
            : redirect()->route(...$doneTarget);
    }
}
