<?php

namespace App\Services;

use App\Models\Revision;
use App\Support\AutosavableFields;
use App\Support\EntitySnapshot;
use App\Support\FieldComparison;
use App\Support\SavePoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Compares an entity at two moments in its history, field by field.
 *
 * The class the compare view iterates; the controller does no diffing and holds
 * no queries. It resolves both {@see EntitySnapshot}s, throws away every field
 * the two moments agree on, and diffs only what is left.
 *
 * ## The skip is the whole optimisation
 *
 * Two snapshots agreeing on a field's *revision id* means that field did not
 * change between them — no diff can say anything, and running one would be
 * pure cost. So `value` is loaded for the changed fields only. Comparing two
 * save points of a novel-length scene therefore hydrates the one field that
 * moved rather than all three.
 */
class RevisionComparison
{
    public function __construct(
        private readonly RevisionSnapshot $snapshots,
        private readonly RevisionDiffer $differ,
    ) {}

    /**
     * Every field that differs between two save points, in registry order.
     *
     * `$from` must be the older point and `$to` the newer one — the pair is
     * never reordered here. Ordering a pair chronologically is the caller's job,
     * because the caller is what accepted two ids from a query string.
     *
     * Unchanged fields are simply absent from the result. A caller that wants to
     * report "N fields unchanged" gets their names by subtracting these from
     * `AutosavableFields::REGISTRY` — the same registry it already uses to
     * validate the `$field` filter.
     *
     * @param  string|null  $field  Narrows the result to one field. It does not change
     *                              which pair of values that field is compared over.
     * @return Collection<int, FieldComparison>
     */
    public function between(Model $entity, SavePoint $from, SavePoint $to, ?string $field = null): Collection
    {
        $before = $this->snapshots->asOf($entity, $from);
        $after = $this->snapshots->asOf($entity, $to);

        $changed = $this->changedFields($entity, $before, $after, $field);

        if ($changed === []) {
            return collect();
        }

        $revisions = $this->hydrate($changed, $before, $after);
        $kinds = AutosavableFields::REGISTRY[AutosavableFields::slugFor($entity::class)][1];

        return collect($changed)->map(function (string $name) use ($kinds, $revisions, $before, $after): FieldComparison {
            $old = $revisions[$before->revisionIdFor($name)] ?? null;
            $new = $revisions[$after->revisionIdFor($name)] ?? null;

            return new FieldComparison(
                field: $name,
                kind: $kinds[$name],
                from: $old,
                to: $new,
                result: $this->differ->diff($kinds[$name], $old?->value, $new?->value),
            );
        })->values();
    }

    /**
     * The registered fields whose two sides resolved to different revisions, in
     * registry order.
     *
     * @return list<string>
     */
    private function changedFields(Model $entity, EntitySnapshot $before, EntitySnapshot $after, ?string $field): array
    {
        $fields = array_keys(AutosavableFields::REGISTRY[AutosavableFields::slugFor($entity::class)][1]);

        return array_values(array_filter($fields, function (string $name) use ($before, $after, $field): bool {
            if ($field !== null && $name !== $field) {
                return false;
            }

            return $before->revisionIdFor($name) !== $after->revisionIdFor($name);
        }));
    }

    /**
     * The revisions the changed fields resolved to, loaded in full — `value`
     * included — and keyed by id.
     *
     * One query for the whole comparison, and the only place in the compare path
     * that touches `value` at all: snapshots resolve moments with ids and
     * timestamps alone. Loading whole models rather than a value map is
     * deliberate — a `FieldComparison` carrying a `Revision` whose `value` was
     * silently missing is a trap, and the rows are already being read.
     *
     * @param  list<string>  $changed
     * @return array<int, Revision>
     */
    private function hydrate(array $changed, EntitySnapshot $before, EntitySnapshot $after): array
    {
        $ids = [];

        foreach ($changed as $field) {
            foreach ([$before->revisionIdFor($field), $after->revisionIdFor($field)] as $id) {
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return Revision::query()
            ->whereIn('id', $ids)
            ->with('user:id,name')
            ->get()
            ->keyBy('id')
            ->all();
    }
}
