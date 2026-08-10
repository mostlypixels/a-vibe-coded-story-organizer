<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary export directory
    |--------------------------------------------------------------------------
    |
    | Where StaticSiteExporter and EpubExporter write the generated .zip/.epub
    | before the controller streams it. The file is meant to live for one
    | request: BinaryFileResponse::deleteFileAfterSend() removes it once the
    | download has streamed.
    |
    | It survives when the response never streams — an aborted download, or a
    | test that asserts on the response instead of sending it. Nothing fails,
    | the directory only grows, so `php artisan exports:purge` sweeps it.
    |
    | The path is a config value so the test suite can point each test at its
    | own directory. The suite runs in parallel (paratest): processes that
    | shared one directory could delete each other's in-flight exports.
    |
    */

    'temp_path' => env('EXPORTS_TEMP_PATH', storage_path('app/exports')),

    /*
    |--------------------------------------------------------------------------
    | Retention window (hours)
    |--------------------------------------------------------------------------
    |
    | How old a leftover export must be before `exports:purge` removes it. The
    | window protects an export that is being generated or streamed right now
    | from a purge running at the same moment.
    |
    */

    'purge_after_hours' => (int) env('EXPORTS_PURGE_AFTER_HOURS', 24),

];
