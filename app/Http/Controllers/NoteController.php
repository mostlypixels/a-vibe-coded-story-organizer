<?php

namespace App\Http\Controllers;

use App\Enums\NoteLinkType;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Controllers\Concerns\ValidatesIndexFilters;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Act;
use App\Models\Chapter;
use App\Models\Notable;
use App\Models\Note;
use App\Models\Project;
use App\Rules\NoteLinkTarget;
use App\Services\RevisionRecorder;
use App\Support\Flash;
use App\Support\LikeSearch;
use App\Support\NoteCategoryTree;
use App\Support\NoteOutline;
use App\Support\PageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NoteController extends Controller
{
    use RedirectsAfterSave;
    use ResolvesIndexSorting;
    use ValidatesIndexFilters;

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->validateIndexFilters($request);
        $request->validate([
            'category' => ['nullable', 'string'],
            'linked' => ['nullable', Rule::enum(NoteLinkType::class)],
            'book' => ['nullable', 'integer', Rule::exists('books', 'id')->where('project_id', $project->id)],
        ]);

        [$sort, $direction] = $this->resolveSorting($request, ['title', 'updated_at'], 'updated_at', 'desc');

        // "none" is the Uncategorized view. A number shows the notes directly in that category.
        $category = $request->query('category');

        $notes = $project->notes()
            ->with('category')
            // One count over all seven link types, not seven relation counts.
            ->addSelect(['links_count' => Notable::query()->selectRaw('count(*)')->whereColumn('notables.note_id', 'notes.id')])
            ->when($category === 'none', fn ($query) => $query->whereNull('note_category_id'))
            ->when(ctype_digit((string) $category), fn ($query) => $query->where('note_category_id', (int) $category))
            ->when($request->filled('linked'), fn ($query) => $query->whereHas(NoteLinkType::from($request->query('linked'))->noteRelation()))
            ->when($request->filled('book'), fn ($query) => $this->whereLinkedInBook($query, (int) $request->query('book')))
            ->when($request->filled('search'), fn ($query) => LikeSearch::whereContains($query, 'title', $request->query('search')))
            ->orderBy($sort, $direction)
            ->paginate(PageSize::resolve($request->user()?->page_size))
            ->withQueryString();

        return view('notes.index', [
            'project' => $project,
            'notes' => $notes,
            'sort' => $sort,
            'direction' => $direction,
            'categoryTree' => NoteCategoryTree::for($project),
            'category' => $category,
            // displayName() of an unnamed book reads the project.
            'books' => $project->books->each->setRelation('project', $project),
        ]);
    }

    /**
     * Keeps the notes linked to the book, or to an act, chapter or scene inside it.
     *
     * @param  Builder<Note>  $query
     */
    private function whereLinkedInBook(Builder $query, int $bookId): void
    {
        $actIds = Act::query()->select('acts.id')->where('acts.book_id', $bookId);
        $chapterIds = Chapter::query()->select('chapters.id')->whereIn('chapters.act_id', $actIds);

        $query->where(fn (Builder $inBook) => $inBook
            ->whereHas('books', fn (Builder $related) => $related->where('books.id', $bookId))
            ->orWhereHas('acts', fn (Builder $related) => $related->where('acts.book_id', $bookId))
            ->orWhereHas('chapters', fn (Builder $related) => $related->whereIn('chapters.act_id', $actIds))
            ->orWhereHas('scenes', fn (Builder $related) => $related->whereIn('scenes.chapter_id', $chapterIds)));
    }

    public function create(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        // The index sidebar links here with the selected category.
        $categoryId = ctype_digit((string) $request->query('category')) ? (int) $request->query('category') : null;

        // "New note" on an entity page passes `?link=<type>:<id>`. An invalid key shows no link.
        $link = $request->old('link', $request->query('link'));
        $linkTarget = NoteLinkTarget::resolveKey($project, $link);

        return view('notes.create', [
            'project' => $project,
            'categoryTree' => NoteCategoryTree::for($project),
            'categoryId' => $categoryId,
            'link' => $linkTarget ? $link : null,
            'linkType' => $linkTarget ? NoteLinkType::fromModel($linkTarget) : null,
            'linkTarget' => $linkTarget,
        ]);
    }

    public function store(StoreNoteRequest $request, Project $project): RedirectResponse
    {
        $note = DB::transaction(function () use ($request, $project) {
            $note = $project->notes()->create($request->safe()->except('link'));
            $target = $request->linkTarget();

            if ($target !== null) {
                $note->linkTo($target);
            }

            return $note;
        });

        return redirect()->route('notes.show', $note);
    }

    public function show(Note $note): View
    {
        $this->authorize('view', $note->project);

        $tree = NoteCategoryTree::for($note->project);

        return view('notes.show', [
            'note' => $note,
            'categoryPath' => $tree->pathOf($note->note_category_id),
            'outline' => NoteOutline::from($note->body),
            'linkGroups' => $note->linkGroups(),
        ]);
    }

    public function edit(Note $note): View
    {
        $this->authorize('update', $note->project);

        return view('notes.edit', [
            'note' => $note,
            'categoryTree' => NoteCategoryTree::for($note->project),
            'linkGroups' => $note->linkGroups(),
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note, RevisionRecorder $recorder): RedirectResponse
    {
        $data = $request->validated();

        $recorder->saveWithManualCheckpoint($note, $data, $request->baseHashes(), $request->user(), fn () => $note->update($data));

        return $this->redirectAfterSave(
            $request,
            ['notes.edit', $note],
            ['projects.notes.index', $note->project],
        );
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('update', $note->project);

        $project = $note->project;
        $note->delete();

        return redirect()->route('projects.notes.index', $project)->with(Flash::SUCCESS, __('Note deleted.'));
    }
}
