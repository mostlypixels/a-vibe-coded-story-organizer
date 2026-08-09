<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowProgressRequest;
use App\Models\Project;
use App\Models\WordCountSnapshot;
use App\Services\WordCountHistory;
use App\Support\WriterDay;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Tools ▸ Progress: the writer's status against their two goals — today's
 * words and the project's running total — plus the chart of the range they
 * pick. The status strip always shows *now* and ignores the range below it.
 */
class ProgressController extends Controller
{
    public function __construct(private readonly WordCountHistory $history) {}

    public function index(Project $project, ShowProgressRequest $request): View
    {
        $this->authorize('view', $project);

        $today = WriterDay::for($project->user);
        $writtenToday = $this->history->series($project, $today, $today)->writtenInRange();

        // The live SUM, not the last snapshot: the strip must never disagree
        // with the dashboard header while a save is still catching up to a
        // snapshot row.
        $totalWords = (int) ($project->sceneQuery()->sum('word_count') ?? 0);

        [$from, $to] = $this->resolveRange($request, $project);
        $series = $this->history->series($project, $from, $to);

        // WordCountSeries fills every day in the range with a zero, so
        // isEmpty() never fires here — the empty state answers "has this
        // project recorded anything, ever", not "is this range empty".
        $hasSnapshots = WordCountSnapshot::query()->where('project_id', $project->id)->exists();

        return view('progress.index', [
            'project' => $project,
            'writtenToday' => $writtenToday,
            'totalWords' => $totalWords,
            'series' => $series,
            'from' => $from,
            'to' => $to,
            'months' => $this->recentMonths($project),
            'hasSnapshots' => $hasSnapshots,
        ]);
    }

    /**
     * The last 12 calendar months in the owner's timezone, newest first, for
     * the range picker's month `<select>`. Shown even where a month has no
     * history — the empty state is a true answer, and a list that changes
     * shape as the writer uses the app is worse.
     *
     * @return array<int, array{from: string, to: string, label: string}>
     */
    private function recentMonths(Project $project): array
    {
        $thisMonth = WriterDay::for($project->user)->startOfMonth();

        return collect(range(0, 11))
            ->map(fn (int $monthsAgo) => $thisMonth->subMonths($monthsAgo))
            ->map(fn (CarbonImmutable $monthStart) => [
                'from' => $monthStart->toDateString(),
                'to' => $monthStart->endOfMonth()->toDateString(),
                'label' => $monthStart->translatedFormat('F Y'),
            ])
            ->all();
    }

    /**
     * The chart's range: the submitted `from`/`to`, or the current month in
     * the *project owner's* timezone when either is absent. Never
     * `now()->startOfMonth()` — see {@see WriterDay}.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(ShowProgressRequest $request, Project $project): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            return [
                CarbonImmutable::parse($request->validated('from'))->startOfDay(),
                CarbonImmutable::parse($request->validated('to'))->startOfDay(),
            ];
        }

        $startOfMonth = WriterDay::for($project->user)->startOfMonth();

        return [$startOfMonth, $startOfMonth->endOfMonth()->startOfDay()];
    }
}
