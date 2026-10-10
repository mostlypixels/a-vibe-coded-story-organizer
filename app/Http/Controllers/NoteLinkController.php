<?php

namespace App\Http\Controllers;

use App\Enums\NoteLinkType;
use App\Http\Requests\StoreNoteLinkRequest;
use App\Models\Book;
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
    /** The picker shows this many candidates at most. */
    private const CANDIDATE_LIMIT = 20;

    public function store(StoreNoteLinkRequest $request, Note $note): RedirectResponse|Response
    {
        $note->linkTo($request->target());

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function destroy(Request $request, Note $note, string $type, int $id): RedirectResponse|Response
    {
        $this->authorize('update', $note->project);

        $note->unlink(NoteLinkType::from($type), $id);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    /** Entities of one type that the note can link to: `[{type, id, label}]`. */
    public function candidates(Request $request, Note $note): JsonResponse
    {
        $project = $note->project;
        $this->authorize('view', $project);

        $request->validate([
            'type' => ['required', 'string', Rule::enum(NoteLinkType::class)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $type = NoteLinkType::from($request->string('type')->value());
        $column = $type->labelColumn();

        $rows = $type->queryFor($project)
            ->when($request->filled('q'), fn ($query) => LikeSearch::whereContains($query, $column, $request->string('q')->value()))
            ->orderBy($column)
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        return response()->json($rows->map(function ($row) use ($type, $project) {
            if ($row instanceof Book) {
                $row->setRelation('project', $project);
            }

            return ['type' => $type->value, 'id' => $row->getKey(), 'label' => $type->labelFor($row)];
        })->values());
    }

    /** Notes of the project that an entity can link to: `[{id, title}]`. */
    public function noteCandidates(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate(['q' => ['nullable', 'string', 'max:255']]);

        $notes = $project->notes()
            ->when($request->filled('q'), fn ($query) => LikeSearch::whereContains($query, 'title', $request->string('q')->value()))
            ->orderBy('title')
            ->limit(self::CANDIDATE_LIMIT)
            ->get(['id', 'title']);

        return response()->json($notes->map(fn (Note $note) => ['id' => $note->id, 'title' => $note->title])->values());
    }
}
