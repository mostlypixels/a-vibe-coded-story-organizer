<?php

namespace App\Support;

use App\Enums\RevisionOrigin;
use App\Services\RevisionHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * One addressable moment in an entity's history: everything one Save wrote,
 * folded back into the single event the writer remembers making.
 *
 * Storage stays per field — the `revisions` table has one immutable row per
 * (field, moment) — but every screen above it thinks in save points. A save
 * that touched three fields is *one* thing to look at, compare against, and
 * undo, not three. `revisions.save_id` is what ties the rows together, and
 * {@see RevisionHistory} is what folds them into this.
 *
 * @see SaveEntry for the per-field half.
 */
final readonly class SavePoint
{
    /**
     * The order that decides a save point's own origin when its rows disagree —
     * most deliberate first.
     *
     * Rows can disagree only through coalescing: an autosave burst that is still
     * open when the writer presses Save leaves an `automatic` row and a `manual`
     * row in the same group. That group is a save the writer *made*, so it reads
     * as `manual`. Anything else would let a deliberate checkpoint hide behind
     * the autosave that happened to precede it.
     *
     * @var list<RevisionOrigin>
     */
    public const ORIGIN_PRECEDENCE = [
        RevisionOrigin::Manual,
        RevisionOrigin::Revert,
        RevisionOrigin::Import,
        RevisionOrigin::Automatic,
        RevisionOrigin::Baseline,
    ];

    /**
     * @param  string  $saveId  The ULID the whole group shares — how a URL names this save.
     * @param  string|null  $authorName  Null when the author's account is gone.
     * @param  string|null  $label  The first label any of its rows carries, e.g. "Saved 24 July 10:43".
     * @param  bool  $isCurrent  This is the entity's newest save point — its live state.
     * @param  string|null  $previousSaveId  The save point immediately before this one, or null
     *                                       at the start of history. What "compare with previous"
     *                                       and "and N more changes" both point at.
     * @param  Collection<int, SaveEntry>  $entries  In `AutosavableFields::REGISTRY` field order,
     *                                               so two save points list their fields the same way.
     */
    public function __construct(
        public string $saveId,
        public CarbonInterface $savedAt,
        public ?string $authorName,
        public ?string $label,
        public RevisionOrigin $origin,
        public bool $isCurrent,
        public ?string $previousSaveId,
        public Collection $entries,
    ) {}

    /**
     * Whether this is the seeded starting point rather than something a writer
     * did — rendered as "Initial value" instead of as a list of changes.
     */
    public function isBaseline(): bool
    {
        return $this->origin === RevisionOrigin::Baseline;
    }

    /**
     * Whether there is anything before this point to compare it against.
     */
    public function hasPrevious(): bool
    {
        return $this->previousSaveId !== null;
    }

    /**
     * The most deliberate origin among a group's rows.
     *
     * @param  iterable<RevisionOrigin>  $origins
     */
    public static function dominantOrigin(iterable $origins): RevisionOrigin
    {
        $present = [];

        foreach ($origins as $origin) {
            $present[$origin->value] = true;
        }

        foreach (self::ORIGIN_PRECEDENCE as $candidate) {
            if (isset($present[$candidate->value])) {
                return $candidate;
            }
        }

        // Unreachable while the precedence list covers the enum — guarded by
        // RevisionHistoryTest so adding a case to RevisionOrigin without adding
        // it here fails loudly rather than silently mislabelling a save point.
        return RevisionOrigin::Automatic;
    }
}
