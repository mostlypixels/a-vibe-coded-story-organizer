<?php

return [

    /*
    |--------------------------------------------------------------------------
    | List page size
    |--------------------------------------------------------------------------
    |
    | The row count every entity index paginates by, read through
    | App\Support\PageSize::resolve(). "default" is the code default used when
    | a user has never chosen a size (users.page_size is null) or their stored
    | value has fallen off "sizes". It is not a setting — there is no
    | installation-wide override.
    |
    | Kept apart from config/search.php and config/revisions.php: those own
    | unrelated per_page values for the search page and revision history, and
    | must not start reading this file.
    |
    */

    'default' => 100,

    'sizes' => [50, 100, 250, 500],

];
