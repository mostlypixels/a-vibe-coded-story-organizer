<?php

namespace App\Services;

use App\Enums\LinkTargetType;
use App\Models\Project;
use App\Support\EntityLinks;

/**
 * Turns the `entity:` links in rendered HTML into app URLs for read views.
 *
 * Every lookup goes through {@see LinkTargetType::queryFor()}, so a link never resolves
 * into another project. It runs one query for each target type in the text.
 *
 * > [!WARNING]
 * > A missing target shows only the author's own link text. Never add the title or
 * > the URL of the target: the id can belong to another project.
 */
class EntityLinkResolver
{
    public function render(string $html, Project $project): string
    {
        $links = EntityLinks::find($html);

        if ($links === []) {
            return $html;
        }

        $found = $this->existingIds($links, $project);

        return EntityLinks::replaceAnchors($html, function (LinkTargetType $type, int $id, string $anchor, string $text) use ($found): string {
            if (isset($found[$type->value][$id])) {
                $url = route($type->showRoute(), $id);

                return EntityLinks::rewrite($anchor, fn (): string => $url);
            }

            return '<span class="entity-link-missing" title="'.e(__('The linked item no longer exists.')).'">'.$text.'</span>';
        });
    }

    /**
     * @param  list<array{type: LinkTargetType, id: int}>  $links
     * @return array<string, array<int, true>> The ids that exist in the project, by type value.
     */
    private function existingIds(array $links, Project $project): array
    {
        $idsByType = [];

        foreach ($links as $link) {
            $idsByType[$link['type']->value][$link['id']] = $link['id'];
        }

        $found = [];

        foreach ($idsByType as $value => $ids) {
            $query = LinkTargetType::from($value)->queryFor($project);

            $existing = $query
                ->whereKey(array_values($ids))
                ->pluck($query->getModel()->getQualifiedKeyName());

            foreach ($existing as $id) {
                $found[$value][(int) $id] = true;
            }
        }

        return $found;
    }
}
