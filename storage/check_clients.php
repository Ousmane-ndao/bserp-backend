<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Dossier;

$failed = [
    'ABDEL WAHAB RAOUL DIEDHIOU',
    'ADAMA DIOME',
    'BABACAR TOURE',
    'BOUSSO BALLY FALL',
    'DAOUDA SAMBOU',
    'DIEYNABOU BA',
    'EL HADJI BABACAR DIABY',
    'HASSANE NDFIAYE',
    'IBRAHIMA SOW BAMBYLOR',
    'MAYO SANE',
    'MBAYE DIAKOUMPA',
    'MOHAMETH NDIAYE',
    'MOR TALLA NDIAYE',
    'MOUHAMADOU NDONGO',
    'MOUSSA SY LOUGA',
    'MOUSTAPHA ABDOUL WADOUL KA',
    'NDEYE ARAME LOUCAR',
    'NDEYE COUBA AIDARA KEBE',
    'NDEYE FATOU FISSIROU',
    'NDEYE KHADY PADAME',
    'OUSMANE SARR',
    'PAPE IBA MAR FAYE',
    'SABOU DIALLO',
    'SAGA LAYE SAMBA',
    'SEYNABOU SECK',
    'SOKHNA KANDJI',
    'TALLA THIAM',
    'THIERNO DIALLO',
];

echo "🔍 Vérification des clients pour les dossiers échoués\n";
echo str_repeat('-', 70) . "\n";

foreach ($failed as $dirName) {
    $client = Client::whereRaw('LOWER(prenom || \' \' || nom) LIKE ?', ['%' . strtolower($dirName) . '%'])->first();
    if ($client) {
        $dossier = Dossier::where('client_id', $client->id)->first();
        $status = $dossier ? "✅ Dossier présent (ID: {$dossier->id})" : "❌ Aucun dossier trouvé";
        echo "✓ $dirName → Client ID {$client->id} ({$client->prenom} {$client->nom}) → $status\n";
    } else {
        echo "✗ $dirName → Client non trouvé dans la base.\n";
    }
}
