<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page width
    |--------------------------------------------------------------------------
    |
    | The share of the window, in percent, that the page content fills from the
    | md breakpoint up. Read through App\Support\PageWidth::resolve(). "steps"
    | is the allow-list, ascending: the quick switcher moves along it.
    | "default" is used when users.page_width is null or has fallen off "steps".
    | It is not a setting — there is no installation-wide override.
    |
    */

    'default' => 70,

    'steps' => [50, 60, 70, 80, 90, 100],

];
