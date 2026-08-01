<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;

$baseDirs = [
    'C:\Users\ousma\Desktop\Doc1Parcoursup',
    'C:\Users\ousma\Desktop\Doc3Parcoursup'
];

$clients = Client::doesntHave('documents')->get();
$imported = 0;
$notFound = [];

foreach ($clients as $client) {
    $nomComplet = trim($client->prenom . ' ' . $client->nom);
    $found = false;
    foreach ($baseDirs as $baseDir) {
        $folder = $baseDir . '/' . $nomComplet;
        if (is_dir($folder)) {
            echo "✅ Dossier trouvé pour {$client->prenom} {$client->nom} (ID: {$client->id}) → $folder\n";
            passthru("php artisan documents:import {$client->id} \"$folder\"");
            $imported++;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $notFound[] = $nomComplet . " (ID: {$client->id})";
        echo "❌ Aucun dossier trouvé pour {$client->prenom} {$client->nom} (ID: {$client->id})\n";
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "✅ Clients importés : $imported\n";
echo "❌ Clients sans dossier source : " . count($notFound) . "\n";
if (!empty($notFound)) {
    echo "Liste :\n";
    foreach ($notFound as $name) {
        echo "  - $name\n";
    }
}
