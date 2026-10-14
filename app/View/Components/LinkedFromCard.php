<?php

namespace App\View\Components;

use App\Enums\LinkTargetType;
use App\Models\EntityLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The texts of the same project that link to one entity with an `entity:` link.
 *
 * It reads the `entity_links` index: one query for the rows, one for each source type.
 * The card is hidden when the list is empty.
 *
 * > [!WARNING]
 * > The row's `project_id` is the source's project. Filter on it: a link can name a
 * > target id of another project, and that target must not list the source.
 */
class LinkedFromCard extends Component
{
    /** The name of each link field, as the author knows it. */
    private const FIELD_LABELS = [
        'description' => 'Description',
        'contents' => 'Prose',
        'body' => 'Text',
    ];

    /** @var Collection<int, array{title: string, kind: string, fields: list<string>, url: string}> */
    public readonly Collection $sources;

    public function __construct(Model $target)
    {
        $this->sources = $this->sourcesOf($target);
    }

    public function render(): View
    {
        return view('components.linked-from-card');
    }

    /** @return Collection<int, array{title: string, kind: string, fields: list<string>, url: string}> */
    private function sourcesOf(Model $target): Collection
    {
        $type = LinkTargetType::fromModel($target);

        $rows = EntityLink::query()
            ->where('project_id', LinkTargetType::projectOf($target)->id)
            ->where('target_type', $type->value)
            ->where('target_id', $target->getKey())
            ->get()
            // A text can link to its own entity. That is not a backlink.
            ->reject(fn (EntityLink $row): bool => $row->source_type === $target->getMorphClass() && $row->source_id === $target->getKey());

        $models = [];

        foreach ($rows->pluck('source_type')->unique() as $class) {
            $ids = $rows->where('source_type', $class)->pluck('source_id')->all();
            $models[$class] = $class::query()->whereKey($ids)->get()->keyBy(fn (Model $model) => $model->getKey());
        }

        return $rows
            ->groupBy(fn (EntityLink $row): string => $row->source_type.':'.$row->source_id)
            ->map(function (Collection $group) use ($models): ?array {
                $first = $group->first();
                $source = $models[$first->source_type][$first->source_id] ?? null;

                return $source === null ? null : $this->describe($source, $group->pluck('source_field')->all());
            })
            ->filter()
            ->sortBy(fn (array $source): string => mb_strtolower($source['title']))
            ->values();
    }

    /**
     * @param  list<string>  $fields
     * @return array{title: string, kind: string, fields: list<string>, url: string}
     */
    private function describe(Model $source, array $fields): array
    {
        $names = collect($fields)
            ->map(fn (string $field): string => self::FIELD_LABELS[$field] ?? $field)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // The project is the only source that is not a link target.
        if ($source instanceof Project) {
            return ['title' => $source->name, 'kind' => 'Project', 'fields' => $names, 'url' => route('projects.show', $source)];
        }

        $type = LinkTargetType::fromModel($source);

        return [
            'title' => $type->labelFor($source),
            'kind' => $type->label(),
            'fields' => $names,
            'url' => route($type->showRoute(), $source),
        ];
    }
}
