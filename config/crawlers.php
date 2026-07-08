<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default hidden mode
    |--------------------------------------------------------------------------
    |
    | Whether a fresh install is hidden from crawlers before anyone touches the
    | settings screen. Default true = hidden (the spec's safe default). This is
    | the documented source of truth used when CrawlerSetting::current() lazily
    | creates the singleton row.
    |
    | NOTE: the value is duplicated by design in the crawler_settings.enabled
    | column default (see the migration). Keep the two equal — this config value
    | seeds the lazy-create path; the column default is a backstop for direct
    | inserts.
    |
    */

    'default_enabled' => (bool) env('CRAWLERS_HIDDEN_DEFAULT', true),

    /*
    |--------------------------------------------------------------------------
    | Disallow path
    |--------------------------------------------------------------------------
    |
    | The path emitted after "Disallow:" in the catch-all block when hidden mode
    | is on. "/" blocks the whole site. Kept here (not hard-coded in the
    | generator) so the robots.txt shape stays configurable and free of magic
    | strings.
    |
    */

    'disallow_path' => '/',

];
