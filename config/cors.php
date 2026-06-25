<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated env var; default covers Capacitor Android WebView (androidScheme=https)
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'https://localhost')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
