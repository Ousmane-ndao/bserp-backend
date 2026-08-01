<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Document;

function guessDocumentType($filename)
{
    // Nettoyer l'encodage : on tente de convertir en UTF-8 si nécessaire
    $encoding = mb_detect_encoding($filename, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding !== 'UTF-8' && $encoding !== false) {
        $filename = mb_convert_encoding($filename, 'UTF-8', $encoding);
    }
    $name = strtolower($filename);

    if (strpos($name, 'cni') !== false || strpos($name, 'passeport') !== false || strpos($name, 'cin') !== false) {
        return 'CNI ou Passeport';
    }
    if (strpos($name, 'photo') !== false) {
        return 'Photo d’identité';
    }
    if (strpos($name, 'bulletin') !== false || strpos($name, 'notes') !== false) {
        return 'Bulletins de notes';
    }
    if (strpos($name, 'diplome') !== false || strpos($name, 'bac') !== false || strpos($name, 'releve') !== false) {
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
    return 'Autre';
}

$docs = Document::where('type_document', 'Autre')->get();
$total = $docs->count();
$updated = 0;

echo "🔍 Correction des types pour $total documents classés en 'Autre'...\n";

foreach ($docs as $doc) {
    try {
        $newType = guessDocumentType($doc->original_filename);
        if ($newType !== 'Autre') {
            $doc->type_document = $newType;
            $doc->save();
            $updated++;
            echo "✅ Document #{$doc->id} ({$doc->original_filename}) → $newType\n";
        } else {
            echo "❌ Document #{$doc->id} ({$doc->original_filename}) reste en 'Autre' (non reconnu)\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erreur sur le document #{$doc->id} ({$doc->original_filename}) : " . $e->getMessage() . "\n";
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "📄 Documents traités : $total\n";
echo "✅ Mis à jour : $updated\n";
echo "❌ Toujours 'Autre' : " . ($total - $updated) . "\n";
