<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportDocumentsForClient extends Command
{
    protected $signature = 'documents:import {client_id : ID du client} {source_dir : Chemin du dossier contenant les documents}';
    protected $description = 'Importe tous les documents d\'un dossier client dans la base de données';

    public function handle()
    {
        $clientId = $this->argument('client_id');
        $sourceDir = $this->argument('source_dir');

        // Vérifier le client
        $client = Client::find($clientId);
        if (!$client) {
            $this->error("Client ID $clientId non trouvé.");
            return 1;
        }

        // Vérifier le dossier
        if (!is_dir($sourceDir)) {
            $this->error("Le dossier source n'existe pas : $sourceDir");
            return 1;
        }

        // Récupérer le dossier associé
        $dossier = Dossier::where('client_id', $clientId)->first();
        if (!$dossier) {
            $this->error("Aucun dossier trouvé pour ce client. Veuillez d'abord créer un dossier.");
            return 1;
        }

        // Lister les fichiers
        $files = glob($sourceDir . '/*.*');
        if (empty($files)) {
            $this->warn("Aucun fichier trouvé dans le dossier.");
            return 0;
        }

        $this->info("Début de l'importation pour le client {$client->prenom} {$client->nom} (ID: $clientId)");
        $bar = $this->output->createProgressBar(count($files));

        $count = 0;
        $errors = [];

        foreach ($files as $filePath) {
            try {
                $filename = basename($filePath);
                $mime = mime_content_type($filePath);
                $size = filesize($filePath);

                // Déterminer le type de document
                $type = $this->guessDocumentType($filename);

                // Copier dans le storage
                $destPath = 'documents/' . $clientId . '/' . $filename;
                Storage::disk('local')->put($destPath, file_get_contents($filePath));

                // Créer l'entrée en base
                Document::create([
                    'client_id' => $clientId,
                    'dossier_id' => $dossier->id,
                    'type_document' => $type,
                    'file_path' => $destPath,
                    'original_filename' => $filename,
                    'size_bytes' => $size,
                    'mime' => $mime,
                    'statut' => 'En attente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $count++;
            } catch (\Exception $e) {
                $errors[] = $filename . ': ' . $e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($count > 0) {
            $this->info("✅ $count document(s) importé(s) avec succès.");
        }

        if (!empty($errors)) {
            $this->warn("⚠️ Erreurs rencontrées :");
            foreach ($errors as $error) {
                $this->warn("  - $error");
            }
        }

        return 0;
    }

    private function guessDocumentType($filename)
    {
        $name = strtolower($filename);
        if (strpos($name, 'cni') !== false || strpos($name, 'passeport') !== false) {
            return 'CNI ou Passeport';
        }
        if (strpos($name, 'bulletin') !== false || strpos($name, 'notes') !== false) {
            return 'Bulletins de notes';
        }
        if (strpos($name, 'diplome') !== false || strpos($name, 'bac') !== false) {
            return 'Diplôme Bac';
        }
        if (strpos($name, 'motivation') !== false) {
            return 'Lettre de motivation';
        }
        if (strpos($name, 'photo') !== false) {
            return 'Photo d\'identité';
        }
        if (strpos($name, 'attestation') !== false) {
            return 'Attestation';
        }
        return 'Autre';
    }
}
