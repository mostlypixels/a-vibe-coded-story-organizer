<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachCodexAttributeRequest;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Services\AttributeTimeline;
use Illuminate\Http\RedirectResponse;

/**
 * Attaches and detaches a (entry, attribute) pair. A different resource from one anchored
 * value: {@see CodexAttributeValueController} owns a single period on an already-attached
 * pair.
 */
class CodexEntryAttributeController extends Controller
{
    /**
     * Attach an attribute to the entry by giving the pair a Start baseline. Idempotent:
     * ensureBaseline() only creates the row when the pair has none yet.
     */
    public function store(AttachCodexAttributeRequest $request, CodexEntry $codexEntry): RedirectResponse
    {
        $codexAttribute = CodexAttribute::findOrFail($request->validated('codex_attribute_id'));

        // Route binding alone is never access control (guidelines): guard against
        // attaching another project's attribute even for the owner.
        abort_unless($codexAttribute->project_id === $codexEntry->project_id, 404);

        (new AttributeTimeline($codexEntry, $codexAttribute))->ensureBaseline('');

        return redirect()->route('codex.edit', $codexEntry);
    }

    /**
     * Detach an attribute by deleting the whole pair's rows. This bypasses
     * AttributeTimeline::removeAt() on purpose: that method guards the Start baseline
     * against leaving a hole, and removing the whole pair leaves no hole to guard.
     */
    public function destroy(CodexEntry $codexEntry, CodexAttribute $codexAttribute): RedirectResponse
    {
        $this->authorize('update', $codexEntry->project);

        abort_unless($codexAttribute->project_id === $codexEntry->project_id, 404);

        $codexEntry->attributeValues()
            ->where('codex_attribute_id', $codexAttribute->id)
            ->delete();

        return redirect()->route('codex.edit', $codexEntry);
    }
}
