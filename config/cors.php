<?php

// return [
//     'paths' => ['api/*', 'sanctum/csrf-cookie'],

//     'allowed_methods' => ['*'],

//     'allowed_origins' => [
//         'http://localhost:3000',
//         'http://localhost:5173',
//         'https://storeadmin.zantechbd.com',
//     ],

//     'allowed_headers' => ['*'],

//     'exposed_headers' => [],

//     'max_age' => 0,

//     'supports_credentials' => true,
// ];

return [
    'paths' => ['*'],  // allow all paths

    'allowed_methods' => ['*'], // allow all methods

    'allowed_origins' => ['*'], // allow all origins

    'allowed_headers' => ['*'], // allow all headers

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // set false if you don’t need cookies/auth
];
