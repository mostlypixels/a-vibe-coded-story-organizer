<?php

namespace App\Services;

use App\Enums\LinkTargetType;
use App\Models\Concerns\HasEntityLinks;
use App\Models\Contracts\Revisionable;
use App\Models\EntityLink;
use App\Models\Project;
use App\Models\Scene;
use App\Support\EntityLinks;
use App\Support\RichTextFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `entity_links` equal to the `entity:` links in the saved text.
 *
 * A sync replaces all rows of the source's link fields in one transaction. It never
 * adds rows one at a time, so the table cannot drift from the text.
 * {@see HasEntityLinks} calls it on save and on delete.
 */
class EntityLinkIndex
{
    private const BATCH_SIZE = 200;

    /**
     * The fields of a model that can hold links: its rich-HTML fields, and scene contents.
     *
     * @param  class-string  $model
     * @return list<string>
     */
    public static function fieldsOf(string $model): array
    {
        $fields = RichTextFields::forModel($model);

        return $model === Scene::class ? [...$fields, 'contents'] : $fields;
    }

    public function sync(Model $source): void
    {
        $this->syncSource($source, null);
    }

    /** Deletes the rows where the model is the source or the target. */
    public function forget(Model $model): void
    {
        $targetType = LinkTargetType::tryFromModel($model);

        EntityLink::query()
            ->where(fn ($q) => $q->where('source_type', $model->getMorphClass())->where('source_id', $model->getKey()))
            ->when($targetType, fn ($q) => $q->orWhere(
                fn ($q) => $q->where('target_type', $targetType?->value)->where('target_id', $model->getKey()),
            ))
            ->delete();
    }

    /** Rebuilds the rows of every source in the project. For writes that skip model events. */
    public function syncProject(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $this->syncSource($project, $project->id);

            foreach (LinkTargetType::cases() as $type) {
                $class = $type->modelClass();

                if (self::fieldsOf($class) === []) {
                    continue;
                }

                $model = new $class;
                $sources = $type->queryFor($project)->lazyById(self::BATCH_SIZE, $model->getQualifiedKeyName(), $model->getKeyName());

                foreach ($sources as $source) {
                    $this->syncSource($source, $project->id);
                }
            }
        });
    }

    /** $projectId is null when the caller does not know it. It is then read only when rows are written. */
    private function syncSource(Model $source, ?int $projectId): void
    {
        $fields = self::fieldsOf($source::class);

        if ($fields === []) {
            return;
        }

        $rows = [];

        foreach ($fields as $field) {
            foreach (EntityLinks::find((string) $source->getAttribute($field)) as $link) {
                $key = "{$field}:{$link['type']->value}:{$link['id']}";
                $rows[$key] = [
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $source->getKey(),
                    'source_field' => $field,
                    'target_type' => $link['type']->value,
                    'target_id' => $link['id'],
                ];
            }
        }

        // The project lookup walks up the tree for a scene. Skip it when there is nothing to write.
        if ($rows !== [] && $projectId === null) {
            assert($source instanceof Revisionable);
            $projectId = $source->revisionProject()->id;
        }

        DB::transaction(function () use ($source, $fields, $rows, $projectId): void {
            EntityLink::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->whereIn('source_field', $fields)
                ->delete();

            if ($rows !== []) {
                EntityLink::query()->insert(array_map(
                    fn (array $row): array => $row + ['project_id' => $projectId],
                    array_values($rows),
                ));
            }
        });
    }
}
