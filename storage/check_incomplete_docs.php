<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Support\DocumentCatalog;

// Récupérer tous les clients qui ont au moins un document
$clients = Client::has('documents')->with('documents')->get();

$incomplete = [];
$complete = 0;

foreach ($clients as $client) {
    $summary = DocumentCatalog::summarizeForClient($client->documents);
    $missing = $summary['missingTypes'] ?? [];
    if (!empty($missing)) {
        $incomplete[] = [
            'id' => $client->id,
            'nom' => $client->prenom . ' ' . $client->nom,
            'progress' => $summary['progressPercent'],
            'missing' => $missing,
        ];
    } else {
        $complete++;
    }
}

echo "📊 Clients avec documents : " . $clients->count() . "\n";
echo "✅ Clients complets : $complete\n";
echo "⚠️ Clients incomplets : " . count($incomplete) . "\n\n";

foreach ($incomplete as $c) {
    echo "🔹 {$c['nom']} (ID: {$c['id']}) – Progression : {$c['progress']}%\n";
    echo "   Manque : " . implode(', ', $c['missing']) . "\n\n";
}
