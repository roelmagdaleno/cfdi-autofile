<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Esta app corre local, con un solo usuario y sin login. No hay modelo de
    | usuarios ni providers, pero el framework lee estas llaves de forma
    | perezosa, asi que el archivo se queda con lo minimo indispensable.
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
        // Registrado en AppServiceProvider. Nunca devuelve un usuario.
        'none' => [
            'driver' => 'none',
        ],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
