<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Destination;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ImportClientsFromExcel extends Command
{
    protected $signature = 'import:clients-excel {file? : Chemin complet vers le fichier Excel}';
    protected $description = 'Importe les clients, dossiers et comptes étudiants depuis un fichier Excel';

    public function handle()
    {
        $filePath = $this->argument('file') ?? storage_path('app/imports/Suivi_Admissions_Visas.xlsx');

        if (!file_exists($filePath)) {
            $this->error("Fichier introuvable : $filePath");
            return 1;
        }

        $this->info('Lecture du fichier Excel...');

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Supprimer l'en-tête (première ligne)
            array_shift($rows);

            // Index des colonnes (A=0, B=1, ...)
            $colNomComplet = 1;       // B
            $colTelephone = 2;        // C
            $colEmail = 3;            // D
            $colMotDePasse = 4;       // E
            $colCampusFrance = 5;     // F
            $colParcoursup = 6;       // G
            $colPays = 7;             // H
            $colEcole = 8;            // I (non utilisé ici)
            $colNiveau = 9;           // J (non utilisé ici)
            $colDateInscription = 10; // K
            $colAdmission = 11;       // L
            $colVisa = 12;            // M (non utilisé ici)
            $colObservations = 13;    // N (si vous voulez la stocker, voir commentaire)

            $total = count($rows);
            $this->info("{$total} lignes à importer.");

            $success = 0;
            $skipped = 0;

            foreach ($rows as $index => $row) {
                // Ignorer les lignes vides
                $nomComplet = trim($row[$colNomComplet] ?? '');
                $email = trim($row[$colEmail] ?? '');
                if (empty($nomComplet) || empty($email)) {
                    $skipped++;
                    continue;
                }

                // --- Destination ---
                $pays = trim($row[$colPays] ?? 'France');
                $region = (stripos($pays, 'France') !== false) ? 'Europe' : 'Autre';
                $destination = Destination::firstOrCreate(
                    ['name' => $pays],
                    ['region' => $region, 'type_compte' => 'B', 'created_at' => now(), 'updated_at' => now()]
                );

                // --- Client ---
                $parts = explode(' ', $nomComplet);
                $nom = array_pop($parts);
                $prenom = implode(' ', $parts);

                $telephone = preg_replace('/[\s.-]+/', '', trim($row[$colTelephone] ?? ''));
                if (empty($telephone))
                    $telephone = null;

                $passwordPlain = trim($row[$colMotDePasse] ?? 'default123');
                $passwordHash = Hash::make($passwordPlain);

                // Date d'inscription (pas utilisée pour l'instant mais peut être stockée)
                $dateInscription = null;
                $rawDate = trim($row[$colDateInscription] ?? '');
                if (!empty($rawDate)) {
                    try {
                        $dateInscription = Carbon::parse($rawDate)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $this->warn("Ligne {$index}: date invalide '{$rawDate}'");
                    }
                }

                // Créer ou récupérer le client
                $client = Client::firstOrCreate(
                    ['email' => $email],
                    [
                        'prenom' => $prenom,
                        'nom' => $nom,
                        'telephone' => $telephone,
                        'destination_id' => $destination->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if (!$client->wasRecentlyCreated) {
                    // Si le client existe déjà, on met à jour ses informations
                    $client->update([
                        'prenom' => $prenom,
                        'nom' => $nom,
                        'telephone' => $telephone,
                        'destination_id' => $destination->id,
                    ]);
                }

                // --- Dossier ---
                $statut = 'En cours';
                if (strtolower(trim($row[$colAdmission] ?? '')) === 'oui') {
                    $statut = 'Admission';
                }
                Dossier::firstOrCreate(
                    ['client_id' => $client->id],
                    [
                        'reference' => 'D-' . $client->id,
                        'statut' => $statut,
                        'destination_id' => $destination->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // --- Student Account (Campus France, Parcoursup) ---
                // Les colonnes existantes dans student_accounts :
                // email, email_password, campus_password, parcoursup_password
                $emailAccount = trim($row[$colEmail] ?? ''); // D : Email
                $emailPassword = trim($row[$colMotDePasse] ?? ''); // E : Mot de passe (email)
                $campusPassword = trim($row[$colCampusFrance] ?? ''); // F : Campus France (mot de passe)
                $parcoursupPassword = trim($row[$colParcoursup] ?? ''); // G : Parcoursup (mot de passe)

                if (Schema::hasTable('student_accounts')) {
                    StudentAccount::updateOrCreate(
                        ['client_id' => $client->id],
                        [
                            'email' => !empty($emailAccount) ? $emailAccount : $client->email,
                            'email_password' => !empty($emailPassword) ? $emailPassword : null,
                            'campus_password' => !empty($campusPassword) ? $campusPassword : null,
                            'parcoursup_password' => !empty($parcoursupPassword) ? $parcoursupPassword : null,
                            'updated_at' => now(),
                        ]
                    );
                } else {
                    $this->warn("Ligne {$index}: table student_accounts n'existe pas, données ignorées.");
                }

                $success++;
                if ($success % 10 === 0)
                    $this->line("Importés : {$success} / {$total}");
            }

            $this->info("✅ Import terminé : {$success} clients importés/mis à jour, {$skipped} ignorés.");
            return 0;
        } catch (\Exception $e) {
            Log::error('Erreur import Excel : ' . $e->getMessage());
            $this->error('Erreur : ' . $e->getMessage());
            return 1;
        }
    }
}
