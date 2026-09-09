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
    | The codex ↔ scene reference cards (codex/show, codex/edit, scenes/show)
    | read this same cap for their own "See all" links.
    |
    */

    'cap' => 5, // rows per column

    /*
    |--------------------------------------------------------------------------
    | Domain-page pagination
    |--------------------------------------------------------------------------
    |
    | There is no `per_page` here any more. The "see all" page uses the reader's
    | own rows-per-page (App\Support\PageSize), like every entity list, so one
    | preference governs every paginated screen.
    |
    | The paging itself is still built by hand in the controller from
    | ProjectSearch's full matched collection, mirroring
    | App\Services\RevisionHistory::forEntity — matching runs in PHP, so a SQL
    | LIMIT/OFFSET would page over the wrong set.
    |
    */

];
