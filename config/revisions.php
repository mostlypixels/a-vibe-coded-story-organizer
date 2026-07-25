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

];
