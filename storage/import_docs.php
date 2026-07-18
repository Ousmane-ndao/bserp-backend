<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Dossier;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

// 📁 MODIFIEZ CE CHEMIN selon l'emplacement de vos dossiers
$baseDir = 'C:\Users\ousma\Desktop\Doc1Parcoursup';

if (!is_dir($baseDir)) {
    echo "❌ Le dossier source n'existe pas : $baseDir\n";
    exit(1);
}

$folders = glob($baseDir . '/*', GLOB_ONLYDIR);
$imported = 0;
$notFound = [];
$totalDocs = 0;

foreach ($folders as $folder) {
    $dirName = basename($folder);
    // Recherche floue du client
    $client = Client::whereRaw('LOWER(prenom || \' \' || nom) LIKE ?', ['%' . strtolower($dirName) . '%'])->first();
    if ($client) {
        // Vérifier/créer un dossier
        $dossier = Dossier::where('client_id', $client->id)->first();
        if (!$dossier) {
            $dossier = Dossier::create([
                'client_id' => $client->id,
                'reference' => 'D-' . $client->id,
                'statut' => 'En cours',
                'destination_id' => $client->destination_id,
            ]);
            echo "✅ Dossier créé pour {$client->prenom} {$client->nom}\n";
        }
        // Importer les fichiers
        $files = glob($folder . '/*.*');
        $count = 0;
        foreach ($files as $file) {
            if (!is_file($file))
                continue;
            $filename = basename($file);
            $mime = mime_content_type($file);
            $size = filesize($file);
            $destPath = 'documents/' . $client->id . '/' . $filename;
            Storage::disk('local')->put($destPath, file_get_contents($file));
            Document::create([
                'client_id' => $client->id,
                'dossier_id' => $dossier->id,
                'type_document' => 'Autre',
                'file_path' => $destPath,
                'original_filename' => $filename,
                'size_bytes' => $size,
                'mime' => $mime,
                'statut' => 'En attente',
            ]);
            $count++;
        }
        echo "📄 {$client->prenom} {$client->nom} : $count document(s) importé(s)\n";
        $imported++;
        $totalDocs += $count;
    } else {
        $notFound[] = $dirName;
        echo "❌ Client non trouvé pour : $dirName\n";
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "✅ Clients traités : $imported\n";
echo "📄 Documents importés : $totalDocs\n";
$nbNotFound = count($notFound);
echo "❌ Clients non trouvés : $nbNotFound\n";
if ($nbNotFound > 0) {
    echo "Liste des non trouvés :\n";
    foreach ($notFound as $name) {
        echo "  - $name\n";
    }
}
