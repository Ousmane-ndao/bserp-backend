<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\DB;

$baseDirs = [
    'C:\\Users\\ousma\\Desktop\\Doc1Parcoursup',
    'C:\\Users\\ousma\\Desktop\\Doc3Parcoursup'
];

// Récupérer les clients sans documents
$clients = Client::doesntHave('documents')->get();

$total = $clients->count();
$imported = 0;
$notFound = [];

echo "🔍 Recherche des dossiers pour $total clients sans documents...\n\n";

foreach ($clients as $client) {
    $nomComplet = trim($client->prenom . ' ' . $client->nom);
    $found = false;

    foreach ($baseDirs as $baseDir) {
        $folder = $baseDir . '/' . $nomComplet;
        if (is_dir($folder)) {
            echo "✅ Dossier trouvé pour {$client->prenom} {$client->nom} (ID: {$client->id}) → $folder\n";
            // Importer les documents
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
