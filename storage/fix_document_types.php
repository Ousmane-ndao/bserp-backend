<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Document;
use Illuminate\Support\Facades\Log;

function guessDocumentType($filename)
{
    // Normaliser : enlever les accents, mettre en minuscules
    $name = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');
    $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $name = strtolower($name);

    // --- RÈGLES DE CLASSIFICATION (ordre d'importance) ---

    // 1. CNI / Passeport / CIN
    if (str_contains($name, 'cni') || str_contains($name, 'passeport') || str_contains($name, 'cin')) {
        return 'CNI ou Passeport';
    }

    // 2. Photo
    if (str_contains($name, 'photo') || str_contains($name, 'image') || str_contains($name, 'jpeg') || str_contains($name, 'jpg') || str_contains($name, 'png')) {
        return 'Photo d’identité';
    }

    // 3. Diplôme Bac / Relevé de notes / Bulletin
    if (str_contains($name, 'diplome') || str_contains($name, 'bac') || str_contains($name, 'releve') || str_contains($name, 'bulletin') || str_contains($name, 'notes') || str_contains($name, 'semestre') || str_contains($name, 'trimestre') || str_contains($name, 'fusion') || str_contains($name, 'premiere') || str_contains($name, 'seconde') || str_contains($name, 'terminale') || str_contains($name, 'annuel') || str_contains($name, 'bts') || str_contains($name, 'licence') || str_contains($name, 'master') || str_contains($name, 'bt')) {
        return 'Bulletins de notes';
    }

    // 4. Certificat / Attestation / Inscription
    if (str_contains($name, 'certificat') || str_contains($name, 'attestation') || str_contains($name, 'inscription') || str_contains($name, 'scolarite')) {
        return 'Certificat d’inscription';
    }

    // 5. Extrait de naissance
    if (str_contains($name, 'extrait') || str_contains($name, 'naissance')) {
        return 'Extrait de naissance';
    }

    // 6. Convocation / Entretien
    if (str_contains($name, 'convocation') || str_contains($name, 'entretien')) {
        return 'Convocation Entretien';
    }

    // 7. Lettre de motivation
    if (str_contains($name, 'motivation') || str_contains($name, 'lettre')) {
        return 'Lettre de motivation';
    }

    // 8. Reçu / Récépissé / Quittance
    if (str_contains($name, 'recu') || str_contains($name, 'recepisse') || str_contains($name, 'quittance')) {
        return 'Reçu';
    }

    // 9. Contrat / Engagement
    if (str_contains($name, 'contrat') || str_contains($name, 'engagement')) {
        return 'Contrat';
    }

    // 10. Formation / Stage
    if (str_contains($name, 'formation') || str_contains($name, 'stage')) {
        return 'Formation';
    }

    // 11. Travail / Parcoursup
    if (str_contains($name, 'travail') || str_contains($name, 'parcoursup')) {
        return 'Travail';
    }

    // 12. Dossier / Pièce identité
    if (str_contains($name, 'dossier') || str_contains($name, 'identite')) {
        return 'Dossier';
    }

    // --- NOUVELLES RÈGLES POUR LES 74 RESTANTS ---
    if (str_contains($name, 'rv demande visa')) {
        return 'Convocation Entretien';
    }
    if (str_contains($name, 'capt formation')) {
        return 'Photo d’identité';
    }
    if (str_contains($name, 'tableau d\'honneur')) {
        return 'Bulletins de notes';
    }
    if (str_contains($name, 'camscanner')) {
        return 'Autre'; // ou 'Scan' si vous voulez créer une catégorie
    }
    if (str_contains($name, 'carte consulaire')) {
        return 'Pièce d’identité';
    }
    if (str_contains($name, 'conduite des stages')) {
        return 'Formation';
    }
    if (str_contains($name, 'planning')) {
        return 'Formation';
    }
    if (str_contains($name, 'materiel scolaire')) {
        return 'Formation';
    }
    if (str_contains($name, 'capture')) {
        return 'Photo d’identité';
    }
    if (str_contains($name, 'extrait')) {
        return 'Extrait de naissance'; // déjà présent, mais on le garde
    }

    // Si aucun mot‑clé n'est trouvé
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
    try {
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
    } catch (\Exception $e) {
        echo "⚠️ Erreur sur ID {$doc->id} ({$doc->original_filename}) : " . $e->getMessage() . "\n";
        $stillOther++;
    }
}

echo "\n========== RÉSUMÉ ==========\n";
echo "📄 Documents traités : $total\n";
echo "✅ Mis à jour : $updated\n";
echo "❌ Toujours 'Autre' : $stillOther\n";