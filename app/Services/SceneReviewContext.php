<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Scene;
use App\Support\RichText;
use Illuminate\Support\Collection;

/**
 * Builds everything the scene-reviewer agent reads before a review, as plain text.
 *
 * Earlier scenes show only their description, except the last few, which show their text.
 * This keeps the output small on a long book. The agent reads more with `scene:show`.
 * Every codex name and alias is listed, because a misspelled name matches no entry.
 */
class SceneReviewContext
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(private readonly CodexAsOfResolver $asOf) {}

    /** @param int $full How many earlier scenes to print in full */
    public function build(Scene $scene, int $full = 3): string
    {
        return implode("\n", $this->lines($scene, $full));
    }

    /**
     * The same text, one entry for each output line. The console command writes them one by one.
     *
     * @param  int  $full  How many earlier scenes to print in full
     * @return list<string>
     */
    public function lines(Scene $scene, int $full = 3): array
    {
        $this->lines = [];

        $scene->load(['chapter.act', 'viewpointCharacter', 'event.locations', 'mentionedEvents', 'codexReferences', 'notes.category']);
        $book = $scene->book();
        $project = $book->project;

        $this->section('Scene');
        $this->line("Project: {$project->name} (ID {$project->id})");
        $this->line("Book: {$book->displayName()} (language: {$book->language->label()})");
        $this->line("Act: {$scene->chapter->act->name}");
        $this->line("Chapter: {$scene->chapter->name}");
        $this->line("Scene: {$scene->name} (ID {$scene->id}, status: {$scene->status->label()})");
        $this->line('Viewpoint character: '.($scene->viewpointCharacter === null ? 'not set' : $scene->viewpointCharacter->name));
        $this->line('Moment: '.($scene->event === null ? 'not set' : $this->eventLabel($scene->event)));

        if ($scene->event?->locations->isNotEmpty()) {
            $this->line('Locations: '.$scene->event->locations->pluck('name')->join(', '));
        }

        if ($scene->mentionedEvents->isNotEmpty()) {
            $this->line('Mentioned events: '.$scene->mentionedEvents->map(fn ($event) => $this->eventLabel($event))->join('; '));
        }

        $this->line('Description: '.RichText::toPlainText($scene->description));

        $this->printCodexNames($project->codexEntries()->with('aliases')->orderBy('name')->get());
        $this->printCodexAsOf($scene);
        $this->printNotes($scene);
        $this->printBookWideNotes($scene);
        $this->printEarlierScenes($scene, max(0, $full));

        $this->section('Scene contents (Markdown)');
        $this->line((string) $scene->contents);

        return $this->lines;
    }

    /** @param Collection<int, CodexEntry> $entries */
    private function printCodexNames(Collection $entries): void
    {
        $this->section('Every codex name and alias in the project');

        foreach ($entries as $entry) {
            $aliases = $entry->aliases->pluck('alias');
            $this->line("- {$entry->name} ({$entry->type->label()})".($aliases->isEmpty() ? '' : ' — aliases: '.$aliases->join(', ')));
        }
    }

    /** Only the entries in this scene, so a large codex does not flood the output. */
    private function printCodexAsOf(Scene $scene): void
    {
        $this->section('Codex entries in this scene, as of its moment');

        $ids = $scene->codexReferences->pluck('id')->push($scene->viewpoint_character_id)->filter();
        $groups = $this->asOf->resolve($scene->project(), $scene->event);
        $printed = [];

        foreach ($groups as $group) {
            foreach ($group['entries'] as $row) {
                if (! $ids->contains($row['entry']->id)) {
                    continue;
                }

                $printed[] = $row['entry']->id;
                $this->line("- {$row['entry']->name}".($row['age'] === null ? '' : " (age {$row['age']->years})"));
                $this->line('    '.RichText::toPlainText($row['entry']->description));

                foreach ($row['attributes'] as $attribute) {
                    $this->line("    {$attribute['name']}: ".$attribute['value']);
                }
            }
        }

        // The resolver drops an entry with nothing to show, or one that does not exist at the
        // moment. In a scene, the second case is a plot hole.
        foreach (CodexEntry::query()->with(['inceptionEvent', 'terminationEvent'])->findMany($ids->diff($printed)) as $entry) {
            if ($entry->existsAt($scene->event)) {
                $this->line("- {$entry->name}");
                $this->line('    '.RichText::toPlainText($entry->description));
            } else {
                $this->line("- {$entry->name}: DOES NOT EXIST at this moment (before its start or after its end)");
            }
        }
    }

    private function printNotes(Scene $scene): void
    {
        $this->section('Notes linked to this scene');

        if ($scene->notes->isEmpty()) {
            $this->line('None.');
        }

        foreach ($scene->notes as $note) {
            $category = $note->note_category_id === null ? 'no category' : $note->category->name;
            $isReview = strcasecmp($category, SceneReviewNoteWriter::CATEGORY) === 0
                && str_starts_with($note->title, SceneReviewNoteWriter::TITLE_PREFIX);
            $this->line("- {$note->title} [{$category}]".($isReview ? ' (earlier review)' : ''));
            $this->line('    '.RichText::toPlainText($note->body));
        }
    }

    /** An unlinked TODO note holds problems for the whole book, so the agent does not report them again. */
    private function printBookWideNotes(Scene $scene): void
    {
        $this->section('Book-wide TODO notes (linked to nothing)');

        $notes = $scene->project()->notes()
            ->whereHas('category', fn ($query) => $query->whereRaw('lower(name) = ?', [mb_strtolower(SceneReviewNoteWriter::CATEGORY)]))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('notables')->whereColumn('notables.note_id', 'notes.id'))
            ->orderBy('id')
            ->get();

        if ($notes->isEmpty()) {
            $this->line('None.');
        }

        foreach ($notes as $note) {
            $this->line("- {$note->title}");
            $this->line('    '.RichText::toPlainText($note->body));
        }
    }

    private function printEarlierScenes(Scene $scene, int $full): void
    {
        $this->section('Earlier scenes in this book');

        $ordered = $scene->book()->chaptersInStoryOrder()
            ->load(['scenes' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->flatMap(fn (Chapter $chapter) => $chapter->scenes);
        $earlier = $ordered->takeUntil(fn (Scene $other) => $other->id === $scene->id)->values();

        if ($earlier->isEmpty()) {
            $this->line('None. This is the first scene.');
        }

        $fullFrom = $earlier->count() - $full;

        foreach ($earlier as $index => $other) {
            $this->line("- {$other->id}  {$other->name}: ".RichText::toPlainText($other->description));

            if ($index >= $fullFrom) {
                $this->line((string) $other->contents);
                $this->line('');
            }
        }
    }

    private function eventLabel(Event $event): string
    {
        return "{$event->title}, ".($event->is_approximate ? 'about ' : '').$event->event_datetime->format('Y-m-d H:i');
    }

    private function line(string $text): void
    {
        $this->lines[] = $text;
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("## {$title}");
    }
}
