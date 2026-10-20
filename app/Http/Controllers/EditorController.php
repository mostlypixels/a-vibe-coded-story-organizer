<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Scene;
use App\Services\EditorCards;
use App\Services\RecentlyEdited;
use App\Services\WordCountHistory;
use App\Support\EditorLayout;
use App\Support\EditorOutline;
use App\Support\SceneNeighbours;
use App\Support\WriterDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Write page: one scene's prose, with side panels for research.
 *
 * A scene switch is a full page load. The load flushes autosave and releases
 * the tab lock, so the page never swaps the editor in place.
 */
class EditorController extends Controller
{
    public function __construct(
        private WordCountHistory $history,
        private RecentlyEdited $recent,
        private EditorCards $cards,
    ) {}

    /** Opens the scene of the project with the newest write, in any book. */
    public function home(Project $project): RedirectResponse|View
    {
        $this->authorize('view', $project);

        $scene = $project->sceneQuery()->latest('updated_at')->latest('id')->first();

        if ($scene !== null) {
            return redirect()->route('scenes.write', $scene);
        }

        return view('editor.empty', [
            'project' => $project,
            'book' => $project->lastBook ?? $project->books()->first(),
        ]);
    }

    public function show(Request $request, Scene $scene): View
    {
        $book = $scene->book();
        $project = $book->project;

        $this->authorize('update', $project);

        $chapter = $scene->chapter;
        $outline = EditorOutline::forBook($book);
        $numbering = $outline->numbering();

        // An unnamed book is the project itself, so the line leaves it out.
        $context = array_filter([
            $book->hasOwnName() ? $book->displayName() : null,
            $numbering->actLabel($chapter->act),
            $numbering->chapterLabel($chapter),
        ]);

        [$previous, $next] = SceneNeighbours::of($scene);

        // The same figure as the dashboard "Today" bar. The policy above proved that the request user owns the project.
        $today = WriterDay::for($request->user());

        return view('editor.show', [
            'scene' => $scene,
            'previous' => $previous,
            'next' => $next,
            'writtenToday' => $this->history->series($project, $today, $today)->writtenInRange(),
            'recentScenes' => $this->recent->scenes($project, config('editor.recent_limit'), 'scenes.write'),
            'project' => $project,
            'book' => $book,
            'outline' => $outline,
            'chapters' => $outline->chapters(),
            'currentChapterId' => $chapter->id,
            'context' => array_values($context),
            'layout' => EditorLayout::resolve($request->user()->editor_layout),
            'cards' => $this->cards->forScene($request->user(), $scene, $project),
        ]);
    }
}
