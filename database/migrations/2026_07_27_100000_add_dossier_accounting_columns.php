<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dossiers', 'montant_total')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->decimal('montant_total', 15, 2)->default(272500.00)->after('date_ouverture');
            });
        }

        if (! Schema::hasColumn('dossiers', 'solde_restant')) {
            Schema::table('dossiers', function (Blueprint $table) {
                $table->decimal('solde_restant', 15, 2)->default(272500.00)->after('montant_total');
            });
        }

        $this->backfillDossierAmounts();
        $this->backfillAvanceNumerosByDossier();
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            if (Schema::hasColumn('dossiers', 'solde_restant')) {
                $table->dropColumn('solde_restant');
            }
            if (Schema::hasColumn('dossiers', 'montant_total')) {
                $table->dropColumn('montant_total');
            }
        });
    }

    private function backfillDossierAmounts(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Utilisation d'une CTE (Common Table Expression) pour PostgreSQL
            DB::statement('
                WITH updates AS (
                    SELECT
                        d.id,
                        COALESCE(dest.montant_total, 272500.00) AS montant_total,
                        ROUND(COALESCE(dest.montant_total, 272500.00) - COALESCE(p.total_paye, 0), 2) AS solde_restant
                    FROM dossiers d
                    LEFT JOIN clients c ON c.id = d.client_id
                    LEFT JOIN destinations dest ON dest.id = c.destination_id
                    LEFT JOIN (
                        SELECT dossier_id, SUM(montant) AS total_paye
                        FROM payments
                        WHERE dossier_id IS NOT NULL
                        GROUP BY dossier_id
                    ) p ON p.dossier_id = d.id
                )
                UPDATE dossiers d
                SET
                    montant_total = u.montant_total,
                    solde_restant = u.solde_restant
                FROM updates u
                WHERE d.id = u.id
            ');
            return;
        }

        // Fallback pour MySQL ou autres drivers (si besoin)
        DB::table('dossiers')
            ->orderBy('id')
            ->chunk(200, function ($dossiers): void {
                foreach ($dossiers as $dossier) {
                    $client = DB::table('clients')->where('id', $dossier->client_id)->first();
                    $montantTotal = 272500.00;

                    if ($client?->destination_id) {
                        $dest = DB::table('destinations')->where('id', $client->destination_id)->first();
                        $montantTotal = (float) ($dest?->montant_total ?? 272500.00);
                    }

                    $totalPaye = (float) DB::table('payments')
                        ->where('dossier_id', $dossier->id)
                        ->sum('montant');

                    DB::table('dossiers')->where('id', $dossier->id)->update([
                        'montant_total' => $montantTotal,
                        'solde_restant' => round($montantTotal - $totalPaye, 2),
                    ]);
                }
            });
    }

    private function backfillAvanceNumerosByDossier(): void
    {
        $dossierIds = DB::table('payments')
            ->whereNotNull('dossier_id')
            ->distinct()
            ->pluck('dossier_id');

        foreach ($dossierIds as $dossierId) {
            $payments = DB::table('payments')
                ->where('dossier_id', $dossierId)
                ->orderBy('date_paiement')
                ->orderBy('id')
                ->get(['id']);

            foreach ($payments as $index => $payment) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['avance_numero' => 'A'.($index + 1)]);
            }
        }
    }
};
