<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Specs tree root
    |--------------------------------------------------------------------------
    |
    | Where the `.specs/` feature-spec tree lives (see .specs/README.md for the
    | full lifecycle). Kept in config — not hard-coded in the spec:draft
    | command — so tests can point the command at a throw-away directory
    | instead of writing into the real tree (which would trip
    | tests/Unit/SpecsStatusConsistencyTest and leak files under paratest).
    |
    */

    'path' => base_path('.specs'),

];
