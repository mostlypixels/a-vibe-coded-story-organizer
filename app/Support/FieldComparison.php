<?php

namespace App\Support;

use App\Enums\FieldKind;
use App\Models\Revision;
use App\Services\RevisionComparison;

/**
 * One field's story between two save points: what it was, what it became, and
 * the rendered diff between them.
 *
 * Produced by {@see RevisionComparison} only for fields that actually changed —
 * an unchanged field is never diffed and never appears here, which is what keeps
 * a compare page about the change rather than about the entity.
 */
final readonly class FieldComparison
{
    /**
     * @param  Revision|null  $from  Null when the field had no revision yet at the older
     *                               point: everything on the newer side is an insertion.
     * @param  Revision|null  $to  Null when the field's history starts after the newer point,
     *                             which only happens for a pair resolved out of order.
     */
    public function __construct(
        public string $field,
        public FieldKind $kind,
        public ?Revision $from,
        public ?Revision $to,
        public RevisionDiffResult $result,
    ) {}

    /**
     * Whether the field came into existence between the two points, so the
     * whole of it reads as new rather than as an edit.
     */
    public function isNewField(): bool
    {
        return $this->from === null;
    }
}
