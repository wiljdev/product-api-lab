<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo User
    |--------------------------------------------------------------------------
    |
    | Credentials of the user created by the DemoUserSeeder so evaluators can
    | request an API token. Always set a strong DEMO_USER_PASSWORD outside of
    | local development.
    |
    */

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo User'),
        'email' => env('DEMO_USER_EMAIL', 'demo@example.com'),
        'password' => env('DEMO_USER_PASSWORD') ?: 'password',
    ],

];
