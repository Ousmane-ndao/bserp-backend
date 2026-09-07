<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private const CACHE_KEY = 'dashboard_full_stats';

    private const CACHE_TTL = 600; // 10 minutes

    public function __invoke(): JsonResponse
    {
        try {
            // Récupère et met en cache les données brutes (array) — ne pas mettre un JsonResponse en cache
            $stats = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return $this->getStats();
            });

            // Si le cache contient une valeur corrompue (par ex. objet sérialisé invalide), on la purge et on régénère
            if (!is_array($stats)) {
                Log::warning('Dashboard cache contained non-array value; clearing cache and regenerating', ['key' => self::CACHE_KEY, 'type' => gettype($stats)]);
                Cache::forget(self::CACHE_KEY);
                $stats = $this->getStats();
                Cache::put(self::CACHE_KEY, $stats, self::CACHE_TTL);
            }

            return response()->json($stats);
        } catch (\Throwable $e) {
            // Log détaillé pour diagnostic
            Log::error('DashboardController error: ' . $e->getMessage(), ['exception' => $e]);
            $message = config('app.debug') ? $e->getMessage() : 'Erreur interne dashboard';
            return response()->json(['error' => $message], 500);
        }
    }

    private function getStats(): array
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth()->toDateString();
        $monthExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(%s, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT(%s, '%%Y-%%m')",
            default => "strftime('%%Y-%%m', %s)",
        };

        // 1. Agrégation massive des dossiers
        $dossierStats = DB::table('dossiers')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN statut = 'En cours' THEN 1 END) as en_cours,
                COUNT(CASE WHEN statut = 'Terminé' THEN 1 END) as termines,
                COUNT(CASE WHEN statut IN ('En cours', 'En attente') THEN 1 END) as ouverts,
                COUNT(CASE WHEN statut IN ('Accepté', 'Visa obtenu', 'Visa refusé') THEN 1 END) as acceptes,
                COUNT(CASE WHEN statut = 'Accepté' THEN 1 END) as acceptes_en_attente,
                COUNT(CASE WHEN statut = 'Visa obtenu' THEN 1 END) as visa_obtenu,
                COUNT(CASE WHEN statut = 'Visa refusé' THEN 1 END) as visa_refuse,
                COUNT(CASE WHEN statut IN ('Refusé', 'Rejeté', 'Visa refusé') THEN 1 END) as refuses,
                COUNT(CASE WHEN statut IN ('En attente', 'En cours', 'En attente visa') THEN 1 END) as en_attente_decision,
                COUNT(CASE WHEN statut = 'Visa obtenu' THEN 1 END) as visas_obtenus,
                COUNT(CASE WHEN statut = 'Visa refusé' THEN 1 END) as visas_refuses,
                COUNT(CASE WHEN date_ouverture = ? THEN 1 END) as aujourdhui,
                COUNT(CASE WHEN date_ouverture >= ? AND date_ouverture <= ? THEN 1 END) as ce_mois,
                AVG(montant_total) as montant_accompagnement
            ", [$today, $startOfMonth, $endOfMonth])
            ->first();

        // 2. Complétude documentaire: indépendante du statut métier.
        $dossiersComplets = Dossier::query()->with('documents:id,dossier_id,type_document')->get()
            ->filter(fn (Dossier $dossier) => $dossier->estComplet())
            ->count();
        $documentsManquants = Dossier::query()->whereDoesntHave('documents')->count();

        // 3. Stats Invoices et Paiements
        $invoiceStats = DB::table('invoices')
            ->selectRaw("
                COUNT(CASE WHEN statut IN (?, ?) THEN 1 END) as en_attente,
                COUNT(CASE WHEN statut = ? THEN 1 END) as pending_count
            ", [Invoice::STATUT_BROUILLON, Invoice::STATUT_ENVOYEE, Invoice::STATUT_ENVOYEE])
            ->first();

        $paymentStats = DB::table('payments')
            ->selectRaw("
                SUM(montant) as total_revenus,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as recents
            ", [now()->subDays(30)])
            ->first();

        // 4. Dossiers par statut (déjà groupé)
        $dossiersParStatut = DB::table('dossiers')
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->all();

        // 5. Tendances Dossiers (Une seule requête au lieu de 6)
        $dossiersTrendRows = DB::table('dossiers')
            ->selectRaw(sprintf($monthExpression, 'date_ouverture').' as ym, COUNT(*) as total')
            ->where('date_ouverture', '>=', $sixMonthsAgo)
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        // 6. Tendances Revenus
        $revenusTrendRows = DB::table('payments')
            ->selectRaw(sprintf($monthExpression, 'date_paiement').' as ym, SUM(montant) as total')
            ->whereNotNull('date_paiement')
            ->where('date_paiement', '>=', $sixMonthsAgo)
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        // Formattage des tendances pour le frontend
        $dossiersTrendMois = [];
        $revenusTrendMois = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $key = $d->format('Y-m');
            $label = $d->locale('fr')->isoFormat('MMM YY');

            $dossiersTrendMois[] = [
                'key' => $key,
                'label' => $label,
                'total' => (int) ($dossiersTrendRows[$key] ?? 0),
            ];
            $revenusTrendMois[] = [
                'key' => $key,
                'label' => $label,
                'total' => (float) ($revenusTrendRows[$key] ?? 0),
            ];
        }

        $acceptationsTotal = (int) $dossierStats->acceptes;
        $acceptationsRepartition = [
            [
                'key' => 'acceptes_en_attente',
                'label' => 'Acceptés en attente',
                'total' => (int) $dossierStats->acceptes_en_attente,
            ],
            [
                'key' => 'visa_obtenu',
                'label' => 'Visa obtenu',
                'total' => (int) $dossierStats->visa_obtenu,
            ],
            [
                'key' => 'visa_refuse',
                'label' => 'Visa refusé',
                'total' => (int) $dossierStats->visa_refuse,
            ],
        ];
        foreach ($acceptationsRepartition as &$acceptation) {
            $acceptation['percentage'] = $acceptationsTotal > 0
                ? round(($acceptation['total'] / $acceptationsTotal) * 100, 1)
                : 0.0;
        }
        unset($acceptation);

        // 7. Dossiers par destination
        $dossiersParDestination = DB::table('dossiers')
            ->join('clients', 'clients.id', '=', 'dossiers.client_id')
            ->join('destinations', 'destinations.id', '=', 'clients.destination_id')
            ->selectRaw('destinations.name as name, COUNT(dossiers.id) as value')
            ->groupBy('destinations.name')
            ->orderByDesc('value')
            ->get()
            ->toArray();

        return [
            'total_clients' => Client::query()->count(),
            'total_dossiers' => (int) $dossierStats->total,
            'dossiers_en_cours' => (int) $dossierStats->en_cours,
            'dossiers_complets' => $dossiersComplets,
            'dossiers_termines' => (int) $dossierStats->termines,
            'dossiers_incomplets' => max(0, (int) $dossierStats->total - $dossiersComplets),
            'dossiers_ouverts' => (int) $dossierStats->ouverts,
            'dossiers_acceptes' => (int) $dossierStats->acceptes,
            'acceptations_repartition' => $acceptationsRepartition,
            'dossiers_refuses' => (int) $dossierStats->refuses,
            'dossiers_en_attente_decision' => (int) $dossierStats->en_attente_decision,
            'visas_obtenus' => (int) $dossierStats->visas_obtenus,
            'visas_refuses' => (int) $dossierStats->visas_refuses,
            'dossiers_aujourdhui' => (int) $dossierStats->aujourdhui,
            'dossiers_ce_mois' => (int) $dossierStats->ce_mois,
            'documents_manquants' => (int) $documentsManquants,
            'paiements_recents' => (int) $paymentStats->recents,
            'total_revenus' => (float) $paymentStats->total_revenus,
            'montant_accompagnement' => $dossierStats->montant_accompagnement !== null
                ? (float) $dossierStats->montant_accompagnement
                : PaymentService::DEFAULT_MONTANT_TOTAL,
            'paiements_en_attente' => (int) $invoiceStats->en_attente,
            'pending_invoices' => (int) $invoiceStats->pending_count,
            'dossiers_par_statut' => $dossiersParStatut,
            'dossiers_trend_mois' => $dossiersTrendMois,
            'revenus_trend_mois' => $revenusTrendMois,
            'dossiers_par_destination' => $dossiersParDestination,
        ];
    }
}
