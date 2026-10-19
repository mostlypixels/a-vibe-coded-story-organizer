<?php

namespace App\Mcp\Tools\Concerns;

use App\Enums\LinkTargetType;
use App\Models\Note;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Rules\NoteLinkTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Turns the `category` and `links` tool arguments of the note tools into rows.
 *
 * A wrong value is a validation error, so Claude sees it as a tool error.
 */
trait ResolvesNoteFields
{
    /**
     * A category ID, or a name without regard to case. Null or an empty value gives null.
     *
     * No tool creates a category: an unknown one is an error.
     */
    protected function noteCategoryId(Project $project, mixed $reference): ?int
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return null;
        }

        $categories = NoteCategory::query()->where('project_id', $project->id);

        if (ctype_digit($reference) && (clone $categories)->whereKey((int) $reference)->exists()) {
            return (int) $reference;
        }

        $matches = $categories->whereRaw('lower(name) = ?', [mb_strtolower($reference)])->orderBy('id')->get(['id', 'name']);

        if ($matches->count() === 1) {
            return $matches->first()->id;
        }

        $problem = $matches->isEmpty()
            ? "No note category matches \"{$reference}\". Use `list` with type note-category to see them."
            : "Several note categories are named \"{$reference}\". Use an ID: ".$matches->map(fn (NoteCategory $category) => "#{$category->id}")->join(', ').'.';

        throw ValidationException::withMessages(['category' => $problem]);
    }

    /**
     * The validation rules for the `links` argument, a list of `<type>:<id>` keys.
     *
     * @return array<string, mixed>
     */
    protected function noteLinkRules(Project $project): array
    {
        return [
            'links' => ['nullable', 'array'],
            'links.*' => ['string', new NoteLinkTarget($project)],
        ];
    }

    /**
     * Makes the links of the note exactly these targets. Call it only after validation passes.
     *
     * @param  list<string>  $keys
     */
    protected function replaceNoteLinks(Note $note, array $keys): void
    {
        $targets = collect($keys)
            ->map(fn (string $key): Model => NoteLinkTarget::resolveKey($note->project, $key))
            ->groupBy(fn (Model $target): string => LinkTargetType::fromModel($target)->value);

        foreach (LinkTargetType::noteLinkableCases() as $type) {
            $ids = $targets->get($type->value, collect())->map->getKey()->unique()->all();

            $note->{$type->noteRelation()}()->sync($ids);
        }
    }

    /**
     * Names the linked entities for the tool reply, like "scene #12, codex #4".
     *
     * @param  list<string>  $keys
     */
    protected function describeLinks(array $keys): string
    {
        return $keys === [] ? 'no links' : implode(', ', array_map(fn (string $key): string => str_replace(':', ' #', $key), array_values(array_unique($keys))));
    }
}
