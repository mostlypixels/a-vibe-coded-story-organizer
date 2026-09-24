<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Snapshot directory
    |--------------------------------------------------------------------------
    |
    | Where `db:backup` writes its SQLite snapshots. The default is on the
    | same disk as the database, so a lost disk takes both. Copy the
    | snapshots somewhere else if they must survive that.
    |
    | The path is a config value so each test can use its own directory.
    | The suite runs in parallel (paratest), and a shared directory lets one
    | process prune the snapshots of another.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Snapshots to keep, per name
    |--------------------------------------------------------------------------
    |
    | `db:backup` keeps the newest snapshots of each name and deletes the
    | older ones. The count is per name, so scheduled snapshots never push
    | out a manual `before-migrate` snapshot. With a snapshot every hour, the
    | default keeps two days.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 48),

    /*
    |--------------------------------------------------------------------------
    | Schedule interval (hours)
    |--------------------------------------------------------------------------
    |
    | The scheduler runs `db:backup` at minute 0 of every Nth hour, counted
    | from midnight. Use 1 to 24. A value that does not divide 24 (for
    | example 5) gives a shorter gap before midnight.
    |
    */

    'every_hours' => (int) env('BACKUP_EVERY_HOURS', 1),

    /*
    |--------------------------------------------------------------------------
    | Default snapshot name
    |--------------------------------------------------------------------------
    |
    | The name for a run without `--name`. The scheduled run uses it.
    |
    */

    'default_name' => env('BACKUP_DEFAULT_NAME', 'scheduled'),

];
