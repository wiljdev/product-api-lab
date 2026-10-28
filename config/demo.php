<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Users
    |--------------------------------------------------------------------------
    |
    | Credentials of the users created by the DemoUserSeeder so evaluators can
    | request API tokens. The editor can write products and prices; the viewer
    | only gets a read-only token, useful to check the 403 responses. Always
    | set strong passwords outside of local development.
    |
    */

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo User'),
        'email' => env('DEMO_USER_EMAIL', 'demo@example.com'),
        'password' => env('DEMO_USER_PASSWORD') ?: 'password',
    ],

    'viewer' => [
        'name' => env('DEMO_VIEWER_NAME', 'Demo Viewer'),
        'email' => env('DEMO_VIEWER_EMAIL', 'viewer@example.com'),
        'password' => env('DEMO_VIEWER_PASSWORD') ?: 'password',
    ],

];
