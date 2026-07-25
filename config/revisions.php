<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default retention window (days)
    |--------------------------------------------------------------------------
    |
    | How long an `automatic`, unlabeled revision survives before it becomes
    | eligible for pruning (Revision::prunable()). This is the default that seeds
    | the lazily-created RevisionSetting::current() singleton, which is what
    | Revision::prunable() actually reads at prune time — so lowering retention in
    | the admin panel takes effect on the next scheduled prune without a deploy.
    |
    */

    'retention_days' => (int) env('REVISIONS_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Coalescing windows (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a run of autosaves to the same (Model, field) keeps overwriting
    | the same open revision row before the next save opens a new one. Keyed
    | "Model.field" with a "default" fallback — read by
    | App\Support\AutosavableFields, never hard-coded per field in the
    | controller.
    |
    */

    'windows' => [
        'Scene.contents' => 60, // seconds
        'default' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-field character caps
    |--------------------------------------------------------------------------
    |
    | Enforced identically by the autosave endpoint and the existing Form
    | Requests, so the two can never drift (handoff.md §9.8). Keyed
    | "Model.field" with a "default" fallback.
    |
    */

    'caps' => [
        'Scene.contents' => 1_000_000,
        'Project.rights' => 1_000,
        'default' => 100_000, // descriptions
    ],

    /*
    |--------------------------------------------------------------------------
    | Visual diff
    |--------------------------------------------------------------------------
    |
    | max_word_complexity — the ceiling on the word-level pass inside a changed
    | block, measured as old_token_count * new_token_count. Above it,
    | App\Services\Diff\VisualHtmlDiffer stops trying to show which words moved
    | and reports the block as removed-and-added instead.
    |
    | Borrowed from wikidiff2's `maxWordLevelDiffComplexity`, and for the same
    | reason: a Myers diff is quadratic in the worst case, so a wholesale
    | rewrite of a long paragraph would otherwise make the request grind. A
    | coarser diff is a far better failure mode than a slow page.
    |
    */

    'diff' => [
        'max_word_complexity' => 2_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | History-row summaries
    |--------------------------------------------------------------------------
    |
    | max_length — how much of a change one history row shows, measured in
    | characters of rendered *text*: the markup around it never counts against
    | the budget. App\Services\RevisionSummarizer spends it outward from the
    | first change, so the thing the row exists to show is never what gets cut.
    |
    | Bounded by length rather than by hunk count on purpose. A find-and-replace
    | on a character's name produces forty hunks, and forty hunks in a list row
    | is unreadable — while "the first hunk only" would be uselessly terse for a
    | one-word edit. Whatever does not fit is reported as "and N more changes",
    | which links to the compare page.
    |
    */

    'summary' => [
        'max_length' => 200, // characters of text
    ],

    /*
    |--------------------------------------------------------------------------
    | History list
    |--------------------------------------------------------------------------
    |
    | per_page — how many *save points* one page of an entity's history shows.
    | Save points, not rows: one Save that touched three fields is one entry on
    | the list, so a page of 20 can render anywhere from 20 to 60 revisions.
    |
    | App\Services\RevisionHistory always fetches one group beyond the page. The
    | extra one is never rendered — it exists so the last row on the page can
    | still name the save point before it for its "compare with previous" link.
    |
    */

    'history' => [
        'per_page' => 20, // save points
    ],

];
