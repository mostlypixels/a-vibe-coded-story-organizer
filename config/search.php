<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Main-page column cap
    |--------------------------------------------------------------------------
    |
    | How many rows the search page shows per result column before it hides the
    | rest behind a "See all N results" link to the domain's own page. Read by
    | the view layer; App\Services\ProjectSearch itself never caps a collection.
    |
    */

    'cap' => 5, // rows per column

    /*
    |--------------------------------------------------------------------------
    | Domain-page pagination
    |--------------------------------------------------------------------------
    |
    | How many rows one page of a single-domain "see all" page shows.
    | Pagination is built by hand in the controller from ProjectSearch's full
    | matched collection, mirroring App\Services\RevisionHistory::forEntity —
    | matching runs in PHP, so a SQL LIMIT/OFFSET would page over the wrong set.
    |
    */

    'per_page' => 20, // rows

];
