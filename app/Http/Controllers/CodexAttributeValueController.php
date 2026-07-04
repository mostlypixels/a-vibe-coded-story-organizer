<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeValueRequest;
use App\Models\CodexAttribute;
use App\Models\CodexAttributeValue;
use App\Models\CodexEntry;
use App\Services\AttributeTimeline;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class CodexAttributeValueController extends Controller
{
    /**
     * Add or update a period for one (entry, attribute) pair.
     *
     * This is deliberately an upsert (AttributeTimeline::upsertAt): posting an anchor that
     * already has a value updates it in place, which is how existing periods — including the
     * Start baseline — are edited. There is therefore no separate update route.
     */
    public function store(StoreAttributeValueRequest $request, CodexEntry $codexEntry, CodexAttribute $codexAttribute): RedirectResponse
    {
        // Authorization is mirrored in the FormRequest. Guard against pointing an entry's
        // timeline at another project's attribute even for the owner (route-binding alone
        // is never enough for access control — guidelines).
        abort_unless($codexAttribute->project_id === $codexEntry->project_id, 404);

        $validated = $request->validated();
        $startEvent = $codexEntry->project->events()->findOrFail($validated['start_event_id']);

        (new AttributeTimeline($codexEntry, $codexAttribute))->upsertAt($startEvent, $validated['value']);

        return redirect()->route('codex.edit', $codexEntry);
    }

    /**
     * Remove a period. The service refuses to drop the Start baseline while other values
     * exist (it would open a hole at the beginning of the timeline — invariant #1); we
     * translate that refusal into a validation error rather than a hard failure.
     */
    public function destroy(CodexAttributeValue $codexAttributeValue): RedirectResponse
    {
        $entry = $codexAttributeValue->entry;

        $this->authorize('update', $entry->project);

        $timeline = new AttributeTimeline($entry, $codexAttributeValue->attribute);

        try {
            $timeline->removeAt($codexAttributeValue->startEvent);
        } catch (RuntimeException $exception) {
            return redirect()->route('codex.edit', $entry)
                ->withErrors(['attribute_value' => $exception->getMessage()]);
        }

        return redirect()->route('codex.edit', $entry);
    }
}
