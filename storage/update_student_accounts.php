<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\StudentAccount;
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
$alreadyComplete = 0;

foreach ($rows as $row) {
    $email = trim($row[3] ?? '');
    $emailPassword = trim($row[4] ?? '');
    $campusPassword = trim($row[5] ?? '');
    $parcoursupPassword = trim($row[6] ?? '');

    if (empty($email)) {
        continue;
    }

    $client = Client::where('email', $email)->first();
    if (!$client) {
        $notFound[] = $email;
        continue;
    }

    $account = StudentAccount::firstOrNew(['client_id' => $client->id]);
    $changes = [];

    // Mettre à jour uniquement les champs vides en base
    if (empty($account->email) && !empty($email)) {
        $account->email = $email;
        $changes[] = 'email';
    }
    if (empty($account->email_password) && !empty($emailPassword)) {
        $account->email_password = $emailPassword;
        $changes[] = 'email_password';
    }
    if (empty($account->campus_password) && !empty($campusPassword)) {
        $account->campus_password = $campusPassword;
        $changes[] = 'campus_password';
    }
    if (empty($account->parcoursup_password) && !empty($parcoursupPassword)) {
        $account->parcoursup_password = $parcoursupPassword;
        $changes[] = 'parcoursup_password';
    }

    if (!empty($changes)) {
        $account->save();
        $updated++;
        echo "✅ {$client->prenom} {$client->nom} : mis à jour (" . implode(', ', $changes) . ")\n";
    } else {
        $alreadyComplete++;
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "✅ Comptes mis à jour : $updated\n";
echo "🔒 Déjà complets : $alreadyComplete\n";
echo "❌ Emails non trouvés dans la base : " . count($notFound) . "\n";
if (!empty($notFound)) {
    echo "Liste des emails non trouvés :\n";
    foreach ($notFound as $email) {
        echo "  - $email\n";
    }
}