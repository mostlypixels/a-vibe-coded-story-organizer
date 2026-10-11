<?php

use App\Support\ImportRules;

return [

    /*
    |--------------------------------------------------------------------------
    | Default maximum archive size (kilobytes)
    |--------------------------------------------------------------------------
    |
    | The largest import archive (.zip) accepted, in kilobytes. This is the
    | documented source of truth used when ImportSetting::current() lazily
    | creates the singleton settings row on a fresh install.
    |
    | Default 204800 KB = 200 MB, defined once as
    | ImportRules::DEFAULT_MAX_ARCHIVE_KILOBYTES. Mirrors the crawler_settings
    | pattern: the value is duplicated by design in the
    | import_settings.max_archive_kilobytes column default (see the migration —
    | migrations deliberately never reference app classes) — keep the two equal.
    | This config value seeds the lazy-create path; the column default is a
    | backstop for direct inserts.
    |
    */

    'default_max_archive_kilobytes' => (int) env('IMPORT_MAX_ARCHIVE_KILOBYTES', ImportRules::DEFAULT_MAX_ARCHIVE_KILOBYTES),

    /*
    |--------------------------------------------------------------------------
    | Background mode
    |--------------------------------------------------------------------------
    |
    | Whether imports run via the queued ProjectImportJob (true) or inline in
    | the request (false). This is a fact about the runtime, not a writer
    | setting: it is true only where a queue worker runs (Docker). Validation
    | always runs in the request; only the graph-import phases are queued.
    |
    */

    'run_in_background' => (bool) env('IMPORT_RUN_IN_BACKGROUND', false),

    /*
    |--------------------------------------------------------------------------
    | Run timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | The longest time one import run can hold its claim (`running_since`).
    | An older claim counts as a crashed run, and a new run can take it over.
    | ProjectImportJob uses the same value as its queue timeout.
    |
    | Keep the queue `retry_after` (DB_QUEUE_RETRY_AFTER, 1860 in Docker) above
    | this value. Otherwise a second worker takes the job while it still runs.
    |
    */

    'timeout_seconds' => (int) env('IMPORT_TIMEOUT_SECONDS', 1800),

    /*
    |--------------------------------------------------------------------------
    | Retention window for unfinished imports (days)
    |--------------------------------------------------------------------------
    |
    | How long an unfinished import keeps its ZIP and extracted folder before
    | `imports:purge` removes them with the import row. After that, the writer
    | cannot resume it. The window also protects an import that runs now.
    |
    */

    'purge_after_days' => (int) env('IMPORT_PURGE_AFTER_DAYS', 7),

];
