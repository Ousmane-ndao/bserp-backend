<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\DB;

$baseDir = 'C:\Users\ousma\Desktop\Doc3parcoursup';

if (!is_dir($baseDir)) {
    die("Le dossier de base n'existe pas.");
}

// Récupérer tous les sous-dossiers
$items = glob($baseDir . '/*', GLOB_ONLYDIR);

$totalImported = 0;
$totalErrors = 0;

foreach ($items as $folder) {
    $folderName = basename($folder);
    echo "Traitement du dossier : $folderName\n";

    // Essayer de trouver le client correspondant
    // On suppose que le nom du dossier est exactement le nom complet (Prénom NOM)
    // On va chercher un client dont le nom complet correspond en ignorant la casse
    $client = Client::whereRaw("CONCAT(prenom, ' ', nom) ILIKE ?", [$folderName])->first();

    if (!$client) {
        // Essayer avec une correspondance plus souple : on sépare prénom et nom
        $parts = explode(' ', $folderName);
        $nom = array_pop($parts);
        $prenom = implode(' ', $parts);
        $client = Client::where('nom', 'ILIKE', $nom)
                        ->where('prenom', 'ILIKE', $prenom)
                        ->first();
    }

    if (!$client) {
        echo "  ❌ Client non trouvé pour le dossier '$folderName'. Ignoré.\n";
        $totalErrors++;
        continue;
    }

    echo "  ✅ Client trouvé : {$client->prenom} {$client->nom} (ID: {$client->id})\n";

    // Importer les documents en utilisant la commande existante
    $command = "php artisan documents:import {$client->id} \"$folder\"";
    echo "  Exécution : $command\n";
    exec($command, $output, $returnCode);

    if ($returnCode === 0) {
        echo "  ✅ Importation réussie.\n";
        $totalImported++;
    } else {
        echo "  ❌ Échec de l'importation.\n";
        $totalErrors++;
    }

    echo "\n";
}

echo "Résumé : $totalImported dossiers importés, $totalErrors échecs.\n";
