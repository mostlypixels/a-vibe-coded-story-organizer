<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ReparentsChildren;
use App\Http\Controllers\Concerns\ResolvesIndexSorting;
use App\Http\Requests\DestroyActRequest;
use App\Http\Requests\MoveActToBookRequest;
use App\Http\Requests\StoreActRequest;
use App\Http\Requests\UpdateActRequest;
use App\Models\Act;
use App\Models\Book;
use App\Support\StoryNumbering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ReparentsChildren;
    use ResolvesIndexSorting;

    public function index(Request $request, Book $book): View
    {
        $this->authorize('view', $book->project);

        [$sort, $direction] = $this->resolveSorting($request, ['name', 'position'], 'position');

        $acts = $book->acts()
            ->withCount('chapters')
            // One grouped query for the whole page, via the act's own scenes()
            // HasManyThrough — a dot-nested relation path like 'chapters.scenes'
            // is not a real relation name and throws BadMethodCallException, so
            // it must go through that relation directly.
            ->withSum('scenes as word_count', 'word_count')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->query('search').'%'))
            // $sort is allow-listed by resolveSorting().
            ->orderBy($sort, $direction)
            ->get();

        // The delete-with-move dialog on each row needs the full set of sibling acts as
        // move destinations, independent of the current search filter above (moving is
        // never limited to what the search happens to match).
        $destinationActs = $book->acts()
            ->orderBy('position')
            ->get(['id', 'name', 'position']);

        return view('acts.index', [
            'book' => $book,
            'acts' => $acts,
            'destinationActs' => $destinationActs,
            'sort' => $sort,
            'direction' => $direction,
            // Built from the whole book, never the (possibly search-filtered)
            // $acts above — a filtered acts list must still show its true numbers.
            'numbering' => StoryNumbering::forBook($book),
        ]);
    }

    public function show(Act $act): View
    {
        $this->authorize('view', $act->book->project);

        $act->load('chapters.scenes')->loadCount(['chapters', 'scenes']);

        return view('acts.show', [
            'act' => $act,
            'wordCount' => (int) $act->scenes()->sum('word_count'),
            'numbering' => StoryNumbering::forBook($act->book),
            'destinationActs' => $act->book->acts()
                ->whereKeyNot($act->getKey())
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function create(Book $book): View
    {
        $this->authorize('update', $book->project);

        return view('acts.create', ['book' => $book]);
    }

    public function store(StoreActRequest $request, Book $book): RedirectResponse
    {
        $book->acts()->create($request->validated());

        return redirect()->route('books.acts.index', $book);
    }

    public function edit(Act $act): View
    {
        $this->authorize('update', $act->book->project);

        // Counts feed the delete-with-move dialog's honest cascade summary: an act's
        // direct children (chapters) plus its grandchildren (scenes, counted through
        // the chapters) — both are destroyed by a plain cascade delete.
        $act->loadCount('chapters');
        $sceneCount = $act->scenes()->count();

        // Every *other* act in the same book is a candidate destination for moving
        // this act's chapters — the same set the book's acts index offers. An empty
        // list collapses the dialog to "delete everything".
        $destinations = $act->book->acts()
            ->whereKeyNot($act->getKey())
            ->orderBy('position')
            ->get();

        // Every *other* book in the same project is a candidate destination for
        // moving this whole act. An empty list hides the move-to-book control —
        // a one-book project has nowhere to send it.
        $destinationBooks = $act->book->project->books()
            ->whereKeyNot($act->book_id)
            ->orderBy('position')
            ->get();

        return view('acts.edit', [
            'act' => $act,
            'sceneCount' => $sceneCount,
            'destinations' => $destinations,
            'destinationBooks' => $destinationBooks,
            'numbering' => StoryNumbering::forBook($act->book),
            'totalActs' => $act->book->acts()->count(),
        ]);
    }

    public function update(UpdateActRequest $request, Act $act): RedirectResponse
    {
        $data = $request->validated();
        $beforeAutosavedFields = $this->snapshotAutosaved($act, $data);

        $act->update($data);

        $this->recordManualSave($act, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['acts.edit', $act], ['books.acts.index', $act->book]);
    }

    /**
     * Reparents a whole act, with its chapters and scenes, onto another book in
     * the same project. Position is set explicitly: the `creating()` hook only
     * fires on insert, and `book_id` is not mass-assignable, so the move goes
     * through `associate()` (the two pitfalls ReparentsChildren documents).
     */
    public function moveToBook(MoveActToBookRequest $request, Act $act): RedirectResponse
    {
        $destination = Book::findOrFail($request->validated('book_id'));

        $act->position = $destination->acts()->max('position') + 1;
        $act->book()->associate($destination);
        $act->save();

        return redirect()->route('acts.edit', $act);
    }

    public function destroy(DestroyActRequest $request, Act $act): RedirectResponse
    {
        // Authorization is handled by DestroyActRequest::authorize() (mirrors the
        // walk-up-to-project check the other actions perform).
        $book = $act->book;

        // Reassignment and deletion must succeed or fail together.
        DB::transaction(function () use ($request, $act, $book) {
            if ($destinationId = $request->validated('move_children_to')) {
                $destination = $book->acts()->findOrFail($destinationId);

                $this->reparentChildren($act, $destination, 'chapters', 'act');
            }

            // Same cascade path as before — just nothing left to cascade if the
            // chapters were reassigned above.
            $act->delete();
        });

        return redirect()->route('books.acts.index', $book);
    }

    public function moveUp(Act $act): RedirectResponse
    {
        $this->reorderSibling($act, $act->book->project, up: true);

        return redirect()->back();
    }

    public function moveDown(Act $act): RedirectResponse
    {
        $this->reorderSibling($act, $act->book->project, up: false);

        return redirect()->back();
    }
}
