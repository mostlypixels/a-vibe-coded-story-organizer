<?php

namespace App\Services;

use App\Support\AutosavableFields;
use App\Support\FieldComparison;
use App\Support\SavePoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the compare page data from two save IDs in the query string.
 *
 * Missing IDs select the newest pair. A reversed pair becomes chronological,
 * because {@see RevisionComparison} expects the older point first.
 */
class RevisionComparePage
{
    public function __construct(
        private readonly RevisionHistory $history,
        private readonly RevisionComparison $comparison,
    ) {}

    /**
     * @return array{
     *     points: Collection<int, SavePoint>,
     *     from: ?SavePoint,
     *     to: ?SavePoint,
     *     comparisons: Collection<int, FieldComparison>,
     *     unchangedFields: list<string>,
     *     savesApart: int,
     * }
     */
    public function build(Model $entity, ?string $field, ?string $fromId, ?string $toId): array
    {
        // Field filters do not limit the save-point pickers.
        $points = $this->history->savePoints($entity);
        [$from, $to] = $this->resolvePair($points, $fromId, $toId);

        if ($from === null || $to === null) {
            return [
                'points' => $points,
                'from' => null,
                'to' => null,
                'comparisons' => collect(),
                'unchangedFields' => [],
                'savesApart' => 0,
            ];
        }

        $comparisons = $this->comparison->between($entity, $from, $to, $field);

        return [
            'points' => $points,
            'from' => $from,
            'to' => $to,
            'comparisons' => $comparisons,
            'unchangedFields' => $this->unchangedFields($entity, $comparisons),
            'savesApart' => abs($points->search($from, strict: true) - $points->search($to, strict: true)),
        ];
    }

    /**
     * @param  Collection<int, SavePoint>  $points  Newest first.
     * @return array{0: ?SavePoint, 1: ?SavePoint} Oldest first.
     */
    private function resolvePair(Collection $points, ?string $fromId, ?string $toId): array
    {
        if ($points->count() < 2) {
            return [null, null];
        }

        if ($fromId === null || $toId === null) {
            return [$points->get(1), $points->get(0)];
        }

        $from = $this->pointOrFail($points, $fromId);
        $to = $this->pointOrFail($points, $toId);

        return $points->search($from, strict: true) < $points->search($to, strict: true)
            ? [$to, $from]
            : [$from, $to];
    }

    /** @param  Collection<int, SavePoint>  $points */
    private function pointOrFail(Collection $points, string $saveId): SavePoint
    {
        $point = $points->firstWhere('saveId', $saveId);

        abort_if($point === null, 404);

        return $point;
    }

    /**
     * @param  Collection<int, FieldComparison>  $comparisons
     * @return list<string> Registered field labels without a change.
     */
    private function unchangedFields(Model $entity, Collection $comparisons): array
    {
        $fields = array_keys(AutosavableFields::fieldsForModel($entity::class));

        return array_values(array_map(
            Str::headline(...),
            array_diff($fields, $comparisons->pluck('field')->all()),
        ));
    }
}
