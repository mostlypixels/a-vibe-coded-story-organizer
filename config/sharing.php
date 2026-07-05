<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scene share link durations
    |--------------------------------------------------------------------------
    |
    | The whitelist of lifetimes an owner may pick from when generating a
    | public share link for a scene. Keys are the human-readable labels shown
    | in the duration <select>; values are `CarbonInterval`-parseable strings
    | passed to `now()->add(...)` when computing `share_expires_at` (task 02).
    |
    | Both label and value are kept identical here for clarity, but the value
    | is what matters — it must always be a string Carbon can parse. The store
    | request validates the submitted value with `Rule::in` against these keys,
    | so no duration is ever hard-coded in a controller or view.
    |
    */

    'scene_link_durations' => [
        '24 hours' => '24 hours',
        '7 days' => '7 days',
        '30 days' => '30 days',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default scene share duration
    |--------------------------------------------------------------------------
    |
    | Which `scene_link_durations` key is preselected in the duration <select>.
    | Must be one of the keys above.
    |
    */

    'scene_link_default_duration' => '7 days',

];
