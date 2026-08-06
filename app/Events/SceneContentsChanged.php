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
 * This is a published seam, not a feature. Nothing in this codebase listens for
 * it. Autosave knows nothing about word counts, and must not learn. Do not add
 * a listener here on this feature's behalf. That belongs to whichever feature
 * needs the count.
 */
class SceneContentsChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Scene $scene) {}
}
