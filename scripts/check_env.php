<?php

// Simple env checker for BSERP backend
// Usage: php scripts/check_env.php

require __DIR__ . '/../vendor/autoload.php';

function env($key, $default = null) {
    $v = getenv($key);
    if ($v === false) return $default;
    return $v;
}

$checks = [
    'APP_KEY' => env('APP_KEY'),
    'APP_ENV' => env('APP_ENV'),
    'APP_URL' => env('APP_URL'),
    'DB_CONNECTION' => env('DB_CONNECTION'),
    'DB_HOST' => env('DB_HOST'),
    'VITE_API_URL' => env('VITE_API_URL'),
    'SANCTUM_STATEFUL_DOMAINS' => env('SANCTUM_STATEFUL_DOMAINS'),
];

echo "BSERP env quick check\n";
echo str_repeat('=', 40) . "\n";
foreach ($checks as $k => $v) {
    $ok = ($v !== null && $v !== '');
    printf("%-25s : %s\n", $k, $ok ? $v : "MISSING");
}

// Additional advice
if (empty($checks['APP_KEY'])) {
    echo "\nAction: APP_KEY is missing. Run 'php artisan key:generate' on the backend host (not in production unless intended).\n";
}
if (empty($checks['VITE_API_URL'])) {
    echo "\nAction: VITE_API_URL is not set. For Vercel, add an environment variable VITE_API_URL=https://your-backend.example.com and redeploy frontend.\n";
}
if (empty($checks['SANCTUM_STATEFUL_DOMAINS'])) {
    echo "\nAction: SANCTUM_STATEFUL_DOMAINS is empty. Add your frontend hosts (localhost:5173, your-production-domain) for Sanctum cookie-based auth.\n";
}

echo "\nCache info:\n";
$driver = env('CACHE_DRIVER', env('CACHE_STORE', 'file'));
printf("- CACHE driver: %s\n", $driver);

echo "\nDone. Follow the recommended actions above to fix missing values.\n";
