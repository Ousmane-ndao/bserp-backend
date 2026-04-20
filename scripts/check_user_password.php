<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

/** Usage: php scripts/check_user_password.php m.ndao@bserp.com "plainpassword" */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
$plain = $argv[2] ?? '';
if ($email === '' || $plain === '') {
    fwrite(STDERR, "Usage: php scripts/check_user_password.php <email> <password>\n");
    exit(1);
}

$user = User::query()->where('email', $email)->first();
if (! $user) {
    echo "NO_USER\n";
    exit(2);
}

$ok = Hash::check($plain, $user->password);
echo $ok ? "MATCH\n" : "NO_MATCH\n";
