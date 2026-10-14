<?php

namespace App\Http\Controllers;

use App\Enums\LinkTargetType;
use App\Http\Requests\StoreNoteLinkRequest;
use App\Models\Note;
use App\Models\Project;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Links between a note and the entities of its project. The link picker posts here
 * from both sides: from a note, and from an entity page.
 */
class NoteLinkController extends Controller
{
    public function store(StoreNoteLinkRequest $request, Note $note): RedirectResponse|Response
    {
        $note->linkTo($request->target());

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function destroy(Request $request, Note $note, string $type, int $id): RedirectResponse|Response
    {
        $this->authorize('update', $note->project);

        $note->unlink(LinkTargetType::from($type), $id);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    /** Entities of one type that the note can link to: `[{type, id, label}]`. */
    public function candidates(Request $request, Note $note): JsonResponse
    {
        $project = $note->project;
        $this->authorize('view', $project);

        $request->validate([
            'type' => ['required', 'string', Rule::enum(LinkTargetType::class)->only(LinkTargetType::noteLinkableCases())],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $type = LinkTargetType::from($request->string('type')->value());

        return response()->json($type->candidates($project, $request->string('q')->value()));
    }

    /** Notes of the project that an entity can link to: `[{id, title}]`. */
    public function noteCandidates(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate(['q' => ['nullable', 'string', 'max:255']]);

        $notes = $project->notes()
            ->when($request->filled('q'), fn ($query) => LikeSearch::whereContains($query, 'title', $request->string('q')->value()))
            ->orderBy('title')
            ->limit(LinkTargetType::CANDIDATE_LIMIT)
            ->get(['id', 'title']);

        return response()->json($notes->map(fn (Note $note) => ['id' => $note->id, 'title' => $note->title])->values());
    }
}
