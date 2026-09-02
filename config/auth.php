<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This app runs locally, with a single user and no login. There is no user
    | model and no providers, but the framework reads these keys lazily, so the
    | file is kept down to the bare minimum.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'none',
        ],
    ],

    'providers' => [
        // Registered in AppServiceProvider. It never returns a user.
        'none' => [
            'driver' => 'none',
        ],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
