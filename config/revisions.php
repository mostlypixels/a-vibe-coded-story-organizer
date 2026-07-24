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

];
