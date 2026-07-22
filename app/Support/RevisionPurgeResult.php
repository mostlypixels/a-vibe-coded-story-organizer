<?php

namespace App\Support;

/**
 * The outcome of one App\Services\RevisionPurger call: how many revisions
 * were (or, in dry-run mode, would be) removed, and how many bytes of
 * `revisions.value` that represents.
 *
 * `sizeBytes` is summed from the `size_bytes` column, never from the `value`
 * column itself — RevisionPurger's queries select/aggregate scalar columns
 * only and never hydrate `value` (00-overview.md's "list queries never
 * hydrate value" invariant).
 */
final class RevisionPurgeResult
{
    public function __construct(
        public readonly int $count,
        public readonly int $sizeBytes,
    ) {}
}
