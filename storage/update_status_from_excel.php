<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Dossier;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'C:\Users\ousma\Desktop\Suivi_Admissions_Visas.xlsx';

if (!file_exists($filePath)) {
    echo "❌ Fichier Excel introuvable : $filePath\n";
    exit(1);
}

echo "📖 Lecture du fichier Excel...\n";

$spreadsheet = IOFactory::load($filePath);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();

array_shift($rows); // enlever l'en-tête

$updated = 0;
$notFound = [];
$debug = []; // pour stocker quelques exemples

foreach ($rows as $index => $row) {
    $email = trim($row[3] ?? '');
    $admission = trim($row[11] ?? '');
    $visa = trim($row[12] ?? '');

    if (empty($email)) {
        continue;
    }

    // Log des 10 premières lignes pour voir les valeurs
    if ($index < 10) {
        $debug[] = "Ligne " . ($index+2) . " : admission='$admission', visa='$visa'";
    }

    $client = Client::where('email', $email)->first();
    if (!$client) {
        $notFound[] = $email;
        echo "❌ Client non trouvé pour l'email : $email\n";
        continue;
    }

    $dossier = Dossier::where('client_id', $client->id)->first();
    if (!$dossier) {
        $notFound[] = "Client ID {$client->id} ($email) sans dossier";
        continue;
    }

    // ---------- Logique de statut (priorité au visa) ----------
    // On normalise les valeurs pour la comparaison
    $admissionLower = strtolower($admission);
    $visaLower = strtolower($visa);

    // Mots-clés pour visa obtenu
    $visaObtenu = ['approuvé', 'approuve', 'obtenu', 'obtenue', 'oui', 'validé', 'valide'];
    $visaRefuse = ['refusé', 'refuse', 'non', 'rejeté', 'rejete'];

    // Mots-clés pour admission
    $admissionOui = ['oui', 'o', 'yes', 'y'];
    $admissionNon = ['non', 'n', 'no'];

    $isVisaObtenu = false;
    $isVisaRefuse = false;
    $isAdmissionOui = false;
    $isAdmissionNon = false;

    foreach ($visaObtenu as $mot) {
        if (strpos($visaLower, $mot) !== false) {
            $isVisaObtenu = true;
            break;
        }
    }
    foreach ($visaRefuse as $mot) {
        if (strpos($visaLower, $mot) !== false) {
            $isVisaRefuse = true;
            break;
        }
    }
    foreach ($admissionOui as $mot) {
        if (strpos($admissionLower, $mot) !== false) {
            $isAdmissionOui = true;
            break;
        }
    }
    foreach ($admissionNon as $mot) {
        if (strpos($admissionLower, $mot) !== false) {
            $isAdmissionNon = true;
            break;
        }
    }

    // Détermination du statut (priorité au visa)
    if ($isVisaObtenu) {
        $statut = 'Visa obtenu';
    } elseif ($isVisaRefuse) {
        $statut = 'Visa refusé';
    } elseif ($isAdmissionNon) {
        $statut = 'Refusé';
    } elseif ($isAdmissionOui) {
        $statut = 'Accepté';
    } else {
        $statut = 'En cours';
    }

    // Mise à jour
    $dossier->statut = $statut;
    $dossier->save();
    $updated++;

    if ($updated % 50 === 0) {
        echo "✅ $updated dossiers mis à jour...\n";
    }
}

// Affichage du debug
echo "\n🔍 Exemples de valeurs lues (10 premières lignes) :\n";
foreach ($debug as $line) {
    echo "  $line\n";
}

echo "\n========== RÉSUMÉ ==========\n";
echo "✅ Dossiers mis à jour : $updated\n";
echo "❌ Non trouvés : " . count($notFound) . "\n";
if (!empty($notFound)) {
    echo "Liste :\n";
    foreach ($notFound as $item) {
        echo "  - $item\n";
    }
}

// Affichage répartition
echo "\n📊 Répartition des statuts après mise à jour :\n";
$stats = Dossier::groupBy('statut')
    ->select('statut', DB::raw('count(*)'))
    ->pluck('count', 'statut');
foreach ($stats as $statut => $count) {
    echo "  $statut : $count\n";
}
