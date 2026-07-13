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
    | Default background mode
    |--------------------------------------------------------------------------
    |
    | Whether imports run via the queued ProjectImportJob (true) or inline in
    | the request (false) by default. Synchronous is the safe default; queued is
    | opt-in. Validation always runs synchronously regardless of this toggle —
    | only the graph-import phases are ever queued.
    |
    | Like default_max_archive_kilobytes, this seeds ImportSetting::current()'s
    | lazy-create path and is mirrored by the import_settings.run_in_background
    | column default. Keep the two equal.
    |
    */

    'default_run_in_background' => (bool) env('IMPORT_RUN_IN_BACKGROUND', false),

];
