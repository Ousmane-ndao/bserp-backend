<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\Process;

$baseDir = 'C:\Users\ousma\Desktop\Doc3parcoursup';
$folders = glob($baseDir . '/*', GLOB_ONLYDIR);

$success = 0;
$failed = 0;
$report = [];

foreach ($folders as $folder) {
    $dirName = basename($folder);

    // Recherche d'un client correspondant (recherche floue) avec syntaxe PostgreSQL
    $client = Client::whereRaw('LOWER(prenom || \' \' || nom) LIKE ?', ['%' . strtolower($dirName) . '%'])->first();

    if ($client) {
        $cmd = "php artisan documents:import {$client->id} \"$folder\"";
        echo "Traitement : $dirName (ID: {$client->id})\n";
        $result = Process::run($cmd);
        if ($result->successful()) {
            $success++;
            $report[] = "✅ $dirName → Client ID {$client->id} ({$client->prenom} {$client->nom})";
        } else {
            $failed++;
            $report[] = "❌ $dirName → Échec de l'import";
        }
    } else {
        $failed++;
        $report[] = "⚠️ $dirName → Aucun client trouvé";
    }
}

echo "\n📊 Résumé :\n";
echo implode("\n", $report);
echo "\n\n✅ Succès : $success\n❌ Échecs : $failed\n";

