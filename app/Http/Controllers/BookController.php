<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsManualRevisions;
use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Controllers\Concerns\ReordersSiblings;
use App\Http\Controllers\Concerns\ReparentsChildren;
use App\Http\Requests\DestroyBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Project;
use App\Models\Scene;
use App\Services\CoverImageService;
use App\Services\RecentlyEdited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class BookController extends Controller
{
    use RecordsManualRevisions;
    use RedirectsAfterSave;
    use ReordersSiblings;
    use ReparentsChildren;

    public function __construct(private CoverImageService $coverImageService) {}

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $books = $project->books()->withCount('acts')->get();

        // One grouped query for every book's word total. Book has no direct
        // scenes relation of its own — a three-level hasManyThrough (book ->
        // act -> chapter -> scene) is not something Eloquent supports — so the
        // chapter -> act chain is joined by hand, the same way Chapter's and
        // Scene's own index queries already join `acts`.
        $wordCounts = Scene::query()
            ->join('chapters', 'chapters.id', '=', 'scenes.chapter_id')
            ->join('acts', 'acts.id', '=', 'chapters.act_id')
            ->whereIn('acts.book_id', $books->pluck('id'))
            ->selectRaw('acts.book_id as book_id, sum(scenes.word_count) as total')
            ->groupBy('acts.book_id')
            ->pluck('total', 'book_id');

        // A book with no scenes has no row in $wordCounts (SQL GROUP BY has
        // nothing to group). A book with no scenes renders "0 words", not blank.
        foreach ($books as $book) {
            $book->word_count = $wordCounts[$book->id] ?? 0;
        }

        return view('books.index', [
            'project' => $project,
            'books' => $books,
            // The delete-with-move dialog on each row needs the full set of
            // sibling books as move destinations.
            'destinationBooks' => $project->books()->get(['id', 'name', 'position']),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('books.create', [
            'project' => $project,
            // A project always already has a book, so any book created here
            // is at least the second — its name is never optional (see
            // Book::hasOwnName()).
            'nameRequired' => $project->books()->exists(),
        ]);
    }

    public function store(StoreBookRequest $request, Project $project): RedirectResponse
    {
        $project->books()->create($request->validated());

        return redirect()->route('projects.books.index', $project);
    }

    /**
     * The book home: the landing the picker and the book crumb point at.
     * Deliberately thin, and deliberately not a second dashboard — the goals
     * and the progress chart stay on the project dashboard.
     */
    public function show(Book $book, RecentlyEdited $recentlyEdited): View
    {
        $this->authorize('view', $book->project);

        return view('books.show', [
            'book' => $book,
            // A book with no scenes has no row to sum, so sum() itself already
            // returns 0 rather than null — the ?? 0 just keeps that explicit,
            // matching the same rule everywhere else a total is shown.
            'wordCount' => $book->sceneQuery()->sum('word_count') ?? 0,
            'recentScenes' => $recentlyEdited->scenes($book),
        ]);
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book->project);

        // Counts feed the delete-with-move dialog's honest cascade summary: a
        // book's direct children (acts) plus its grandchildren (chapters) and
        // great-grandchildren (scenes) — all destroyed by a plain cascade delete.
        $book->loadCount('acts');
        $chapterCount = $book->chapterQuery()->count();
        $sceneCount = $book->sceneQuery()->count();

        // Every *other* book in the same project is a candidate destination for
        // moving this book's acts — the same set the books index offers. An
        // empty list collapses the dialog to "delete everything".
        $destinations = $book->project->books()->whereKeyNot($book->getKey())->get();

        return view('books.edit', [
            'book' => $book,
            'chapterCount' => $chapterCount,
            'sceneCount' => $sceneCount,
            'destinations' => $destinations,
            // Optional only while this is the project's sole book (see
            // StoreBookRequest / UpdateBookRequest).
            'nameRequired' => $destinations->isNotEmpty(),
            'isLastBook' => $book->project->books()->count() === 1,
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox) out of the plain attribute update and resolve it separately.
        $data = $request->safe()->except(['cover_image', 'remove_cover_image']);

        // Snapshot before the update below overwrites these in memory — see
        // RecordsManualRevisions::snapshotAutosaved()'s docblock.
        $beforeAutosavedFields = $this->snapshotAutosaved($book, $data);

        // The previous file is only unlinked *after* a successful save, so a failed
        // write never leaves the row pointing at a file we already deleted.
        $previousCover = $book->cover_image;
        $storedCover = null;

        if ($request->hasFile('cover_image')) {
            $storedCover = $this->coverImageService->store(
                $request->file('cover_image'),
                CoverImageService::BOOK_COVER_DIRECTORY
            );
            $data['cover_image'] = $storedCover;
        } elseif ($request->boolean('remove_cover_image')) {
            $data['cover_image'] = null;
        }

        try {
            $book->update($data);
        } catch (Throwable $exception) {
            // The row write failed after the new file landed — unlink it before
            // rethrowing so the failure never leaves an orphan file behind
            // (mirrors CodexMediaService::store()'s store-then-unlink pattern).
            $this->coverImageService->delete($storedCover);

            throw $exception;
        }

        // A new upload replaces the old file; the remove checkbox clears it. Either way
        // the previous file is now safe to delete post-commit.
        if ($storedCover !== null || $request->boolean('remove_cover_image')) {
            $this->coverImageService->delete($previousCover);
        }

        $this->recordManualSave($book, $beforeAutosavedFields);

        return $this->redirectAfterSave($request, ['books.edit', $book], ['projects.books.index', $book->project]);
    }

    public function destroy(DestroyBookRequest $request, Book $book): RedirectResponse
    {
        // A project must always keep at least one book.
        abort_if($book->project->books()->count() === 1, 403);

        $project = $book->project;

        // Reassignment and deletion must succeed or fail together.
        DB::transaction(function () use ($request, $book, $project) {
            if ($destinationId = $request->validated('move_children_to')) {
                $destination = $project->books()->findOrFail($destinationId);

                $this->reparentChildren($book, $destination, 'acts', 'book');
            }

            // Same cascade path as before — just nothing left to cascade if the
            // acts were reassigned above.
            $book->delete();
        });

        return redirect()->route('projects.books.index', $project);
    }

    /**
     * The nav book picker. The dashboard carries no book in its URL, so
     * TrackActiveProject cannot record the choice on the way through — this
     * action writes `last_book_id` itself, then sends the writer to the
     * project dashboard.
     */
    public function select(Book $book): RedirectResponse
    {
        $this->authorize('view', $book->project);

        // Assigned directly: last_book_id is kept out of Project::$fillable,
        // the same rule TrackActiveProject follows.
        $book->project->last_book_id = $book->id;
        $book->project->save();

        return redirect()->route('projects.show', $book->project);
    }

    public function moveUp(Book $book): RedirectResponse
    {
        $this->reorderSibling($book, $book->project, up: true);

        return redirect()->back();
    }

    public function moveDown(Book $book): RedirectResponse
    {
        $this->reorderSibling($book, $book->project, up: false);

        return redirect()->back();
    }
}
