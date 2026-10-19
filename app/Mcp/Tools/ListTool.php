<?php

namespace App\Mcp\Tools;

use App\Enums\CodexEntryType;
use App\Mcp\Support\ListType;
use App\Mcp\Support\ToolText;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\CodexEntry;
use App\Models\Event;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Plotline;
use App\Models\Project;
use App\Support\CountPhrase;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list')]
#[Description('List the rows of one type in a project: plotlines, events, notes (grouped by category), note categories, characters, locations or organizations. Shows ID, name and a short description. Use `show` for the full row.')]
#[IsReadOnly]
class ListTool extends Tool
{
    use ReturnsToolErrors;

    /** Rows per call. A longer list continues with `offset`. */
    public const LIMIT = 200;

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('Project ID.')->required(),
            'type' => $schema->string()->enum(array_column(ListType::cases(), 'value'))->description('What to list.')->required(),
            'offset' => $schema->integer()->description('Rows to skip. Use the offset the previous call printed.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->guard(function () use ($request): Response {
            $data = $request->validate([
                'project' => ['required', 'integer'],
                'type' => ['required', Rule::enum(ListType::class)],
                'offset' => ['nullable', 'integer', 'min:0'],
            ]);

            $project = Project::query()->findOrFail($data['project']);
            $type = ListType::from($data['type']);
            $offset = (int) ($data['offset'] ?? 0);

            [$rows, $hasMore] = match ($type) {
                ListType::Plotline => $this->plotlines($project, $offset),
                ListType::Event => $this->events($project, $offset),
                ListType::Note => $this->notes($project, $offset),
                ListType::NoteCategory => $this->noteCategories($project, $offset),
                ListType::Character => $this->codex($project, CodexEntryType::Character, $offset),
                ListType::Location => $this->codex($project, CodexEntryType::Location, $offset),
                ListType::Organization => $this->codex($project, CodexEntryType::Organization, $offset),
            };

            $lines = ["# {$this->heading($type)} in {$project->name} (#{$project->id})", ''];

            if ($rows === []) {
                $lines[] = $offset > 0 ? 'No more rows.' : 'None.';
            }

            array_push($lines, ...$rows);

            if ($hasMore) {
                array_push($lines, '', 'More rows exist. Call again with offset '.($offset + self::LIMIT).'.');
            }

            return Response::text(implode("\n", $lines));
        });
    }

    /** @return array{0: list<string>, 1: bool} */
    private function plotlines(Project $project, int $offset): array
    {
        $query = $project->plotlines()->getQuery()->select(['id', 'name', 'description', 'is_main'])->orderBy('name')->orderBy('id');

        return $this->page($query, $offset, fn (Plotline $plotline): string => "- #{$plotline->id} {$plotline->name}".($plotline->is_main ? ' (main)' : '').$this->tail($plotline->description));
    }

    /** @return array{0: list<string>, 1: bool} */
    private function events(Project $project, int $offset): array
    {
        $query = $project->events()->getQuery()
            ->select(['id', 'title', 'description', 'event_datetime', 'is_approximate'])
            ->orderBy('event_datetime')->orderBy('id');

        return $this->page($query, $offset, fn (Event $event): string => "- #{$event->id} ".ToolText::event($event).$this->tail($event->description));
    }

    /**
     * Notes print grouped by category. The query sorts by category name first. A group splits only at a page edge.
     *
     * @return array{0: list<string>, 1: bool}
     */
    private function notes(Project $project, int $offset): array
    {
        // The body can be long and a list never prints it.
        $query = Note::query()
            ->where('notes.project_id', $project->id)
            ->leftJoin('note_categories', 'note_categories.id', '=', 'notes.note_category_id')
            ->select(['notes.id', 'notes.title', 'notes.note_category_id', 'note_categories.name as category_name'])
            ->orderBy('note_categories.name')->orderBy('notes.title')->orderBy('notes.id');

        $lines = [];
        $currentCategory = false;
        [$page, $hasMore] = $this->pageRows($query, $offset);

        foreach ($page as $note) {
            if ($currentCategory !== $note->note_category_id) {
                $currentCategory = $note->note_category_id;
                if ($lines !== []) {
                    $lines[] = '';
                }

                array_push($lines, $currentCategory === null ? '## No category' : "## {$note->getAttribute('category_name')} (category #{$currentCategory})", '');
            }

            $lines[] = "- #{$note->id} {$note->title}";
        }

        return [$lines, $hasMore];
    }

    /** @return array{0: list<string>, 1: bool} */
    private function noteCategories(Project $project, int $offset): array
    {
        $query = $project->noteCategories()->getQuery()->withCount('notes')->orderBy('name')->orderBy('id');

        return $this->page($query, $offset, function (NoteCategory $category): string {
            $parent = $category->parent_id === null ? '' : ", inside #{$category->parent_id}";

            return "- #{$category->id} {$category->name} (".CountPhrase::make((int) $category->getAttribute('notes_count'), 'note', 'notes')."{$parent})";
        });
    }

    /** @return array{0: list<string>, 1: bool} */
    private function codex(Project $project, CodexEntryType $type, int $offset): array
    {
        $query = $project->codexEntries()->getQuery()->where('type', $type->value)->with('aliases')->orderBy('name')->orderBy('id');

        return $this->page($query, $offset, function (CodexEntry $entry): string {
            $aliases = $entry->aliases->pluck('alias');

            return "- #{$entry->id} {$entry->name}".($aliases->isEmpty() ? '' : ' (aliases: '.$aliases->join(', ').')').$this->tail($entry->description);
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): string  $format
     * @return array{0: list<string>, 1: bool}
     */
    private function page(Builder $query, int $offset, Closure $format): array
    {
        [$rows, $hasMore] = $this->pageRows($query, $offset);

        return [$rows->map($format)->values()->all(), $hasMore];
    }

    /**
     * One row more than the limit tells the caller whether another page exists.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{0: Collection<int, TModel>, 1: bool}
     */
    private function pageRows(Builder $query, int $offset): array
    {
        $rows = $query->offset($offset)->limit(self::LIMIT + 1)->get();

        return [$rows->take(self::LIMIT)->values(), $rows->count() > self::LIMIT];
    }

    private function heading(ListType $type): string
    {
        return match ($type) {
            ListType::Plotline => 'Plotlines',
            ListType::Event => 'Events',
            ListType::Note => 'Notes',
            ListType::NoteCategory => 'Note categories',
            ListType::Character => 'Characters',
            ListType::Location => 'Locations',
            ListType::Organization => 'Organizations',
        };
    }

    private function tail(?string $description): string
    {
        $short = ToolText::short($description);

        return $short === '' ? '' : " — {$short}";
    }
}
