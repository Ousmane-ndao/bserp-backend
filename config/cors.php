<?php

$origins = env('CORS_ALLOWED_ORIGINS');
$allowedOrigins = $origins
    ? array_values(array_filter(array_map('trim', explode(',', (string) $origins))))
    : [
        'https://bserp.vercel.app',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://localhost:5173',
        'http://192.168.1.8:8080',   // ← ajout pour ton IP locale
    ];

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.vercel\.app$#',
        '#^https://bserp[a-z0-9-]*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    'supports_credentials' => false, // ou true selon ta config (mais false est plus courant)

];