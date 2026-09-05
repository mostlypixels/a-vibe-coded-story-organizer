<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default locale
    |--------------------------------------------------------------------------
    |
    | Used for guests and users who did not select a locale. Must be a `supported` key.
    |
    */

    'default' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | The 24 official EU languages plus `en-US`. `carbon` is the Carbon/ICU locale
    | code date formatting resolves against. `name` is the endonym, so a reader sees
    | their own language's name regardless of which locale is currently active.
    |
    */

    'supported' => [
        'bg' => ['name' => 'Български', 'carbon' => 'bg'],
        'hr' => ['name' => 'Hrvatski', 'carbon' => 'hr'],
        'cs' => ['name' => 'Čeština', 'carbon' => 'cs'],
        'da' => ['name' => 'Dansk', 'carbon' => 'da'],
        'nl' => ['name' => 'Nederlands', 'carbon' => 'nl'],
        'en' => ['name' => 'English', 'carbon' => 'en'],
        'en-US' => ['name' => 'English (US)', 'carbon' => 'en_US'],
        'et' => ['name' => 'Eesti', 'carbon' => 'et'],
        'fi' => ['name' => 'Suomi', 'carbon' => 'fi'],
        'fr' => ['name' => 'Français', 'carbon' => 'fr'],
        'de' => ['name' => 'Deutsch', 'carbon' => 'de'],
        'el' => ['name' => 'Ελληνικά', 'carbon' => 'el'],
        'hu' => ['name' => 'Magyar', 'carbon' => 'hu'],
        'ga' => ['name' => 'Gaeilge', 'carbon' => 'ga'],
        'it' => ['name' => 'Italiano', 'carbon' => 'it'],
        'lv' => ['name' => 'Latviešu', 'carbon' => 'lv'],
        'lt' => ['name' => 'Lietuvių', 'carbon' => 'lt'],
        'mt' => ['name' => 'Malti', 'carbon' => 'mt'],
        'pl' => ['name' => 'Polski', 'carbon' => 'pl'],
        'pt' => ['name' => 'Português', 'carbon' => 'pt'],
        'ro' => ['name' => 'Română', 'carbon' => 'ro'],
        'sk' => ['name' => 'Slovenčina', 'carbon' => 'sk'],
        'sl' => ['name' => 'Slovenščina', 'carbon' => 'sl'],
        'es' => ['name' => 'Español', 'carbon' => 'es'],
        'sv' => ['name' => 'Svenska', 'carbon' => 'sv'],
    ],

];
