<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * One row in a section landing page's "Recently edited" list.
 *
 * A flat, view-ready shape: the controller resolves the label, the edit URL and
 * the optional parent context (an act name, a chapter path, an event date) once,
 * so `x-recent-list` never has to know which model it is rendering.
 *
 * `$updatedAt` is the entity's `updated_at` — ANY write, not only a prose change.
 * That is the point: the list answers "where was I last working?", so a reorder
 * or a status change belongs in the answer too. Entities with no `updated_at`
 * (Tag, CodexAlias, the immutable Revision) cannot appear in these lists.
 */
final readonly class RecentItem
{
    /**
     * How many rows each list shows before the "View all" link takes over.
     */
    public const LIMIT = 5;

    /**
     * `$imageUrl` is the entity's cover, when it has one — a codex entry's
     * Cover media row, a chapter's `cover_image`. Null both for an entity type
     * with no cover field and for one that simply has no cover yet; the list
     * reserves the thumbnail column only when at least one row fills it.
     *
     * `$contextSegments` is `$context` split into breadcrumb parts — the list
     * draws a chevron between them, like the page breadcrumb. A scene uses it
     * for "Book 1 › Act 1 › Chapter 1: <name>". Set one or the other, never both.
     *
     * @param  list<string>|null  $contextSegments
     */
    public function __construct(
        public string $label,
        public string $url,
        public CarbonInterface $updatedAt,
        public ?string $context = null,
        public ?string $imageUrl = null,
        public ?array $contextSegments = null,
    ) {}
}
