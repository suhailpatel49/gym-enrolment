<?php

return [
    'email' => env('GYM_EMAIL', 'memberships@example.com'),
    'tablet_pin' => env('TABLET_PIN', ''),
    'admin' => [
        'name' => env('ADMIN_NAME', 'Incline Fitness Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
