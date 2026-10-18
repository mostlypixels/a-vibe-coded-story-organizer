<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Editor page
    |--------------------------------------------------------------------------
    |
    | Limits and defaults for the Write page. Read through
    | App\Support\EditorLayout. "min_width" and "max_width" are CSS pixels for
    | one side panel. "recent_limit" is the row count of the recent scenes list.
    | "layout" is used for every key that users.editor_layout does not hold.
    | "sections" values mean "collapsed".
    |
    */

    'min_width' => 200,

    'max_width' => 640,

    'recent_limit' => 15,

    'layout' => [
        'left' => ['width' => 280, 'collapsed' => false],
        'right' => ['width' => 360, 'collapsed' => false],
        'sections' => ['codex' => false, 'notes' => false],
    ],

];
