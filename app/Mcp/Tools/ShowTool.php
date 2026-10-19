<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ShowType;
use App\Mcp\Support\ToolText;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\Plotline;
use App\Models\Project;
use App\Models\Scene;
use App\Services\CodexAttributeSheets;
use App\Support\CountPhrase;
use App\Support\StoryNumbering;
use App\Support\WordCountFormat;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('show')]
#[Description('Print every readable field of one entity: project, book, act, chapter, scene (with its full contents), plotline, event, note or codex entry (with aliases and attribute values). Rich fields print as stored HTML.')]
#[IsReadOnly]
class ShowTool extends Tool
{
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(array_column(ShowType::cases(), 'value'))->description('The kind of entity.')->required(),
            'id' => $schema->integer()->description('The entity ID.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->guard(function () use ($request): Response {
            $data = $request->validate([
                'type' => ['required', Rule::enum(ShowType::class)],
                'id' => ['required', 'integer'],
            ]);

            $id = (int) $data['id'];

            $lines = match (ShowType::from($data['type'])) {
                ShowType::Project => $this->project(Project::query()->findOrFail($id)),
                ShowType::Book => $this->book(Book::query()->findOrFail($id)),
                ShowType::Act => $this->act(Act::query()->findOrFail($id)),
                ShowType::Chapter => $this->chapter(Chapter::query()->findOrFail($id)),
                ShowType::Scene => $this->scene(Scene::query()->findOrFail($id)),
                ShowType::Plotline => $this->plotline(Plotline::query()->findOrFail($id)),
                ShowType::Event => $this->event(Event::query()->findOrFail($id)),
                ShowType::Note => $this->note(Note::query()->findOrFail($id)),
                ShowType::Codex => $this->codex(CodexEntry::query()->findOrFail($id)),
            };

            return Response::text(implode("\n", $lines));
        });
    }

    /** @return list<string> */
    private function project(Project $project): array
    {
        $books = $project->books()->orderBy('position')->orderBy('id')->withCount('acts')->get();
        $words = $project->sceneQuery()->sum('word_count');

        $lines = [
            "# Project #{$project->id}: {$project->name}",
            '',
            'Genre: '.($project->getRawOriginal('genre') ?? 'not set'),
            'Daily word goal: '.($project->daily_word_goal ?? 'not set'),
            'Total word goal: '.($project->total_word_goal ?? 'not set'),
            'Words: '.WordCountFormat::text((int) $words),
            '',
            '## Description (HTML)',
            ToolText::html($project->description),
            '',
            '## Books',
            '',
        ];

        foreach ($books as $book) {
            $book->setRelation('project', $project);
            $lines[] = "- #{$book->id} {$book->displayName()} (".CountPhrase::make($book->acts_count, 'act', 'acts').')';
        }

        return $lines;
    }

    /** @return list<string> */
    private function book(Book $book): array
    {
        $book->load('project');
        $acts = $book->acts()->orderBy('position')->orderBy('id')->get();

        $lines = [
            "# Book #{$book->id}: {$book->displayName()}",
            '',
            "Project: #{$book->project->id} {$book->project->name}",
            'Own name: '.($book->name ?? 'none, uses the project name'),
            "Language: {$book->language->label()}",
            'Author: '.($book->author ?? 'not set'),
            'Publisher: '.($book->publisher ?? 'not set'),
            'Rights: '.($book->rights ?? 'not set'),
            'ISBN: '.($book->isbn ?? 'not set'),
            'Words: '.WordCountFormat::text((int) $book->sceneQuery()->sum('word_count')),
            '',
            '## Description (HTML)',
            ToolText::html($book->description),
        ];

        foreach (['dedication', 'acknowledgements', 'preface', 'postface'] as $field) {
            array_push($lines, '', '## '.ucfirst($field).' (Markdown)', trim((string) $book->{$field}) === '' ? '(empty)' : trim((string) $book->{$field}));
        }

        array_push($lines, '', '## Acts', '');

        foreach ($acts as $act) {
            $lines[] = "- #{$act->id} {$act->name}";
        }

        return $lines;
    }

    /** @return list<string> */
    private function act(Act $act): array
    {
        $act->load(['book.project', 'chapters' => fn ($query) => $query->orderBy('position')->orderBy('id')->withCount('scenes')]);
        $numbering = StoryNumbering::forBook($act->book);

        $lines = [
            "# {$numbering->actLabel($act)} (act #{$act->id})",
            '',
            "Book: #{$act->book->id} {$act->book->displayName()}",
            '',
            '## Description (HTML)',
            ToolText::html($act->description),
            '',
            '## Chapters',
            '',
        ];

        foreach ($act->chapters as $chapter) {
            $lines[] = "- #{$chapter->id} {$chapter->name} (".CountPhrase::make($chapter->scenes_count, 'scene', 'scenes').')';
        }

        return $lines;
    }

    /** @return list<string> */
    private function chapter(Chapter $chapter): array
    {
        $chapter->load(['act.book.project', 'scenes' => fn ($query) => $query->select(['id', 'chapter_id', 'name', 'status', 'position', 'word_count'])->orderBy('position')->orderBy('id')]);
        $numbering = StoryNumbering::forBook($chapter->act->book);

        $lines = [
            "# {$numbering->chapterLabel($chapter)} (chapter #{$chapter->id})",
            '',
            "Act: #{$chapter->act->id} {$chapter->act->name}",
            "Book: #{$chapter->act->book->id} {$chapter->act->book->displayName()}",
            '',
            '## Description (HTML)',
            ToolText::html($chapter->description),
            '',
            '## Scenes',
            '',
        ];

        foreach ($chapter->scenes as $scene) {
            $lines[] = "- #{$scene->id} {$scene->name} [{$scene->status->label()}, ".WordCountFormat::text($scene->word_count).']';
        }

        return $lines;
    }

    /** @return list<string> */
    private function scene(Scene $scene): array
    {
        $scene->load(['chapter.act.book.project', 'event', 'viewpointCharacter', 'mentionedEvents', 'notes.category', 'codexReferences']);
        $book = $scene->book();
        $numbering = StoryNumbering::forBook($book);

        $lines = [
            "# {$numbering->sceneLabel($scene)} (scene #{$scene->id})",
            '',
            "Book: #{$book->id} {$book->displayName()}",
            "Chapter: #{$scene->chapter->id} {$scene->chapter->name}",
            "Act: #{$scene->chapter->act->id} {$scene->chapter->act->name}",
            "Status: {$scene->status->label()}",
            'Words: '.WordCountFormat::text($scene->word_count),
            'Viewpoint character: '.($scene->viewpointCharacter === null ? 'not set' : "#{$scene->viewpointCharacter->id} {$scene->viewpointCharacter->name}"),
            'Event: '.($scene->event === null ? 'not set' : "#{$scene->event->id} ".ToolText::event($scene->event)),
            'Mentioned events: '.($scene->mentionedEvents->isEmpty() ? 'none' : $scene->mentionedEvents->map(fn (Event $event) => "#{$event->id} ".ToolText::event($event))->join('; ')),
            'Codex entries named in the text: '.($scene->codexReferences->isEmpty() ? 'none' : $scene->codexReferences->map(fn (CodexEntry $entry) => "#{$entry->id} {$entry->name}")->join(', ')),
            '',
            '## Description (HTML)',
            ToolText::html($scene->description),
            '',
            '## Linked notes',
            '',
        ];

        if ($scene->notes->isEmpty()) {
            $lines[] = 'None.';
        }

        foreach ($scene->notes as $note) {
            $lines[] = "- #{$note->id} {$note->title} [".($note->note_category_id === null ? 'no category' : $note->category->name).']';
        }

        array_push($lines, '', '## Contents (Markdown)', (string) $scene->contents);

        return $lines;
    }

    /** @return list<string> */
    private function plotline(Plotline $plotline): array
    {
        $plotline->load(['events' => fn ($query) => $query->orderBy('event_datetime')->orderBy('events.id')]);

        $lines = [
            "# Plotline #{$plotline->id}: {$plotline->name}",
            '',
            'Color: '.($plotline->color ?? 'not set'),
            'Main plotline: '.($plotline->is_main ? 'yes' : 'no'),
            '',
            '## Description (HTML)',
            ToolText::html($plotline->description),
            '',
            '## Events',
            '',
        ];

        if ($plotline->events->isEmpty()) {
            $lines[] = 'None.';
        }

        foreach ($plotline->events as $event) {
            $lines[] = "- #{$event->id} ".ToolText::event($event);
        }

        return $lines;
    }

    /** @return list<string> */
    private function event(Event $event): array
    {
        $event->load(['plotlines', 'locations', 'scenes' => fn ($query) => $query->select(['id', 'event_id', 'name'])->orderBy('id')]);

        $lines = [
            "# Event #{$event->id}: {$event->title}",
            '',
            "Date: {$event->event_datetime->format('Y-m-d H:i')}",
            'Fixed: '.($event->is_fixed ? 'yes' : 'no'),
            'Approximate: '.($event->is_approximate ? 'yes' : 'no'),
            'Plotlines: '.($event->plotlines->isEmpty() ? 'none' : $event->plotlines->map(fn (Plotline $plotline) => "#{$plotline->id} {$plotline->name}")->join(', ')),
            'Locations: '.($event->locations->isEmpty() ? 'none' : $event->locations->map(fn (CodexEntry $entry) => "#{$entry->id} {$entry->name}")->join(', ')),
            'Scenes: '.($event->scenes->isEmpty() ? 'none' : $event->scenes->map(fn (Scene $scene) => "#{$scene->id} {$scene->name}")->join(', ')),
            '',
            '## Description (HTML)',
            ToolText::html($event->description),
        ];

        return $lines;
    }

    /** @return list<string> */
    private function note(Note $note): array
    {
        $note->load('category');

        $lines = [
            "# Note #{$note->id}: {$note->title}",
            '',
            'Category: '.($note->category === null ? 'none' : "#{$note->category->id} {$note->category->name}"),
            '',
            '## Links',
            '',
        ];

        $groups = $note->linkGroups();

        if ($groups === []) {
            $lines[] = 'None.';
        }

        foreach ($groups as $group) {
            $type = $group['type'];
            $lines[] = "- {$type->label()}: ".$group['items']->map(fn (Model $item) => "#{$item->getKey()} {$type->labelFor($item)}")->join(', ');
        }

        array_push($lines, '', '## Body (HTML)', ToolText::html($note->body));

        return $lines;
    }

    /** @return list<string> */
    private function codex(CodexEntry $entry): array
    {
        $entry->load(['project', 'aliases', 'inceptionEvent', 'terminationEvent', 'attributeValues.startEvent']);
        $aliases = $entry->aliases->pluck('alias');

        $lines = [
            "# {$entry->type->label()} #{$entry->id}: {$entry->name}",
            '',
            'Aliases: '.($aliases->isEmpty() ? 'none' : $aliases->join(', ')),
            "{$entry->type->inceptionLabel()}: ".($entry->inceptionEvent === null ? 'not set' : ToolText::event($entry->inceptionEvent)),
            'Ends: '.($entry->terminationEvent === null ? 'not set' : ToolText::event($entry->terminationEvent)),
            '',
            '## Description (HTML)',
            ToolText::html($entry->description),
            '',
            '## Attributes',
            '',
        ];

        $sheets = app(CodexAttributeSheets::class)->attached($entry, $entry->project->startEvent());

        if ($sheets->isEmpty()) {
            $lines[] = 'None.';
        }

        foreach ($sheets as $sheet) {
            $lines[] = "- {$sheet['attribute']->name}: ".($sheet['baseline']?->value ?: '(empty)');

            foreach ($sheet['periods'] as $period) {
                $lines[] = "    - from {$period->startEvent->title} ({$period->startEvent->event_datetime->format('Y-m-d H:i')}): ".($period->value ?: '(empty)');
            }
        }

        return $lines;
    }
}
