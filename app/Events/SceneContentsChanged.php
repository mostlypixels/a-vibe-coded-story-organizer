<?php

namespace App\Events;

use App\Http\Controllers\FieldAutosaveController;
use App\Models\Scene;
use App\Services\SceneReferenceMatcher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by {@see FieldAutosaveController} whenever a coarse
 * trigger (blur / Ctrl-S / form submit — never a bare debounce tick) saves
 * `Scene.contents`, alongside the same condition that runs
 * {@see SceneReferenceMatcher::syncScene()}.
 *
 * This is a published seam, not a feature: nothing in this codebase listens for it
 * yet. `.specs/draft/word-count`'s planned rollup listener is the intended
 * subscriber — autosave itself has no knowledge of word counts (handoff.md §9.10).
 * Do not add a listener here on this feature's behalf; that belongs to whichever
 * spec actually needs the count.
 */
class SceneContentsChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Scene $scene) {}
}
