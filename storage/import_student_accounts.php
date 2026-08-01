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

// Supprimer l'en-tête
array_shift($rows);

$total = count($rows);
$updated = 0;
$notFound = [];

foreach ($rows as $index => $row) {
    // Colonnes (A=0, B=1, C=2, D=3, E=4, F=5, G=6, ...)
    $email = trim($row[3] ?? '');
    $emailPassword = trim($row[4] ?? '');
    $campusPassword = trim($row[5] ?? '');
    $parcoursupPassword = trim($row[6] ?? '');

    if (empty($email)) {
        echo "⚠️ Ligne " . ($index+2) . " : email vide, ignorée.\n";
        continue;
    }

    // Rechercher le client par email
    $client = Client::where('email', $email)->first();
    if (!$client) {
        $notFound[] = $email;
        echo "❌ Client non trouvé pour l'email : $email\n";
        continue;
    }

    // Créer ou mettre à jour l'enregistrement student_accounts
    StudentAccount::updateOrCreate(
        ['client_id' => $client->id],
        [
            'email' => $email,
            'email_password' => $emailPassword ?: null,
            'campus_password' => $campusPassword ?: null,
            'parcoursup_password' => $parcoursupPassword ?: null,
            'updated_at' => now(),
        ]
    );

    $updated++;
    if ($updated % 50 === 0) {
        echo "✅ $updated lignes traitées...\n";
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "✅ Comptes étudiants mis à jour : $updated\n";
echo "❌ Emails non trouvés : " . count($notFound) . "\n";
if (!empty($notFound)) {
    echo "Liste des emails non trouvés :\n";
    foreach ($notFound as $email) {
        echo "  - $email\n";
    }
}
