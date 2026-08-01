<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Document;
use Illuminate\Support\Facades\Log;

function guessDocumentType($filename)
{
    $name = strtolower($filename);
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name); // enlève les accents

    if (strpos($name, 'cni') !== false || strpos($name, 'passeport') !== false || strpos($name, 'cin') !== false) {
        return 'CNI ou Passeport';
    }
    if (strpos($name, 'photo') !== false) {
        return 'Photo d’identité';
    }
    if (strpos($name, 'bulletin') !== false || strpos($name, 'notes') !== false || strpos($name, 'releve') !== false) {
        return 'Bulletins de notes';
    }
    if (strpos($name, 'diplome') !== false || strpos($name, 'bac') !== false) {
        return 'Diplôme Bac';
    }
    if (strpos($name, 'motivation') !== false) {
        return 'Lettre de motivation';
    }
    if (strpos($name, 'attestation') !== false || strpos($name, 'inscription') !== false) {
        return 'Attestation';
    }
    if (strpos($name, 'certificat') !== false) {
        return 'Certificat de scolarité';
    }
    if (strpos($name, 'convocation') !== false) {
        return 'Convocation Entretien';
    }
    if (strpos($name, 'dossier') !== false) {
        return 'Dossier';
    }
    if (strpos($name, 'identite') !== false) {
        return 'Pièce d\'identité';
    }
    // Ajoutez d'autres règles selon vos besoins
    return 'Autre';
}

echo "🔍 Correction des types pour les documents classés en 'Autre'...\n";

$docs = Document::where('type_document', 'Autre')->get();
$total = $docs->count();
$updated = 0;
$stillOther = 0;

if ($total === 0) {
    echo "✅ Aucun document classé en 'Autre' à traiter.\n";
    exit(0);
}

foreach ($docs as $doc) {
    $newType = guessDocumentType($doc->original_filename);
    if ($newType !== 'Autre') {
        $doc->type_document = $newType;
        $doc->save();
        $updated++;
        echo "✅ Document #{$doc->id} ({$doc->original_filename}) → $newType\n";
    } else {
        $stillOther++;
        echo "❌ Document #{$doc->id} ({$doc->original_filename}) reste en 'Autre' (non reconnu)\n";
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "📄 Documents traités : $total\n";
echo "✅ Mis à jour : $updated\n";
echo "❌ Toujours 'Autre' : $stillOther\n";
