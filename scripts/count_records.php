<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'DB: '.config('database.default').' @ '.config('database.connections.'.config('database.default').'.host').'/'.config('database.connections.'.config('database.default').'.database').PHP_EOL;
echo 'clients='.App\Models\Client::count().PHP_EOL;
echo 'dossiers='.App\Models\Dossier::count().PHP_EOL;
echo 'documents='.App\Models\Document::count().PHP_EOL;
