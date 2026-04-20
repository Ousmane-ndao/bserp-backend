<?php

/**
 * Usage: php scripts/verify_initial_passwords.php
 * Reads storage/app/secrets/initial-user-passwords.txt and checks Hash::check for each user.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$path = storage_path('app/secrets/initial-user-passwords.txt');
if (! is_file($path)) {
    fwrite(STDERR, "Fichier introuvable: {$path}\n");
    exit(1);
}

$lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
$ok = 0;
$fail = 0;

foreach ($lines as $line) {
    if (! str_contains($line, '|')) {
        continue;
    }
    $parts = array_map('trim', explode('|', $line));
    if (count($parts) < 3) {
        continue;
    }
    [, $email, $plain] = $parts;
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue;
    }

    $user = User::query()->where('email', $email)->first();
    if (! $user) {
        echo "MANQUANT  {$email}\n";
        $fail++;

        continue;
    }

    if (Hash::check($plain, $user->password)) {
        echo "OK        {$email}\n";
        $ok++;
    } else {
        echo "ÉCHEC     {$email}\n";
        $fail++;
    }
}

echo "\nRésumé: {$ok} OK, {$fail} problème(s).\n";
exit($fail > 0 ? 2 : 0);
