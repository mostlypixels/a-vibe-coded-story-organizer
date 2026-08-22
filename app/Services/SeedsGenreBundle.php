<?php

namespace App\Services;

use App\Enums\Genre;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Models\Project;
use App\Models\User;
use App\Support\Bundles\BundleAct;
use App\Support\Bundles\BundleAttribute;
use App\Support\Bundles\Bundles;
use App\Support\Bundles\BundleSampleEntry;
use Illuminate\Support\Facades\DB;

/**
 * Creates one project for a user and applies its genre bundle. The single seed
 * path behind both the onboarding web flow and `app:seed-project`, so the two
 * never drift.
 *
 * > [!WARNING]
 * > Runs with model events ON. It relies on `Project::created` to build the
 * > first book, the main plotline, and the Start/End bookends, then layers the
 * > bundle on top. Never call it inside `WithoutModelEvents` — the book,
 * > plotline, and bookends would be missing, and it must not create a second
 * > book.
 *
 * Blank genre creates the project and applies nothing. Attribute values are
 * written only through {@see AttributeTimeline} at the Start event, to keep the
 * leading-anchor invariant. v1 bundles seed no scenes, so there is no
 * SceneReferenceMatcher call; a bundle that ever adds scenes must run the
 * matcher last.
 */
class SeedsGenreBundle
{
    public function seed(User $user, Genre $genre, string $name): Project
    {
        return DB::transaction(function () use ($user, $genre, $name) {
            $project = $user->projects()->create([
                'name' => $name,
                'genre' => $genre,
            ]);

            $bundle = Bundles::for($genre);

            $attributes = $this->seedAttributes($project, $bundle->attributes());
            $this->seedTags($project, $bundle->tags());
            $this->seedSampleEntries($project, $bundle->sampleEntries(), $attributes);
            $this->seedSkeleton($project, $bundle->skeleton());

            return $project;
        });
    }

    /**
     * @param  array<int, BundleAttribute>  $attributes
     * @return array<string, CodexAttribute> attribute name => model
     */
    private function seedAttributes(Project $project, array $attributes): array
    {
        $created = [];

        foreach ($attributes as $index => $attribute) {
            $created[$attribute->name] = $project->codexAttributes()->create([
                'name' => $attribute->name,
                'applies_to' => $attribute->appliesTo,
                'position' => $index + 1,
            ]);
        }

        return $created;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function seedTags(Project $project, array $tags): void
    {
        foreach ($tags as $name) {
            $project->tags()->firstOrCreate(['name' => $name]);
        }
    }

    /**
     * @param  array<int, BundleSampleEntry>  $entries
     * @param  array<string, CodexAttribute>  $attributes
     */
    private function seedSampleEntries(Project $project, array $entries, array $attributes): void
    {
        foreach ($entries as $entry) {
            $model = $project->codexEntries()->create([
                'type' => $entry->type,
                'name' => $entry->name,
                'description' => $entry->description,
            ]);

            $this->attachTags($project, $model, $entry->tags);

            foreach ($entry->attributeValues as $attributeName => $value) {
                $attribute = $attributes[$attributeName];

                (new AttributeTimeline($model, $attribute))->ensureBaseline($value);
            }
        }
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    private function attachTags(Project $project, CodexEntry $entry, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(
            fn (string $name): int => $project->tags()->firstOrCreate(['name' => $name])->id,
        );

        $entry->tags()->syncWithoutDetaching($tagIds);
    }

    /**
     * Build the act/chapter outline on the project's first book (made by the
     * `created` hook). Positions are one-based, in bundle order.
     *
     * @param  array<int, BundleAct>  $skeleton
     */
    private function seedSkeleton(Project $project, array $skeleton): void
    {
        if ($skeleton === []) {
            return;
        }

        $book = $project->books()->first();

        foreach ($skeleton as $actIndex => $act) {
            $actModel = $book->acts()->create([
                'name' => $act->name,
                'position' => $actIndex + 1,
            ]);

            foreach ($act->chapters as $chapterIndex => $chapterName) {
                $actModel->chapters()->create([
                    'name' => $chapterName,
                    'position' => $chapterIndex + 1,
                ]);
            }
        }
    }
}
