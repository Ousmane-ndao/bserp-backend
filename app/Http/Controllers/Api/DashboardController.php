<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const CACHE_KEY = 'dashboard_full_stats';

    private const CACHE_TTL = 600; // 10 minutes

    public function __invoke(): JsonResponse
    {
        // On essaye de récupérer tout le dashboard du cache
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return response()->json($this->getStats());
        });
    }

    private function getStats(): array
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth()->toDateString();

        // 1. Agrégation massive des dossiers
        $dossierStats = DB::table('dossiers')
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN statut = 'En cours' THEN 1 END) as en_cours,
                COUNT(CASE WHEN statut = 'Terminé' THEN 1 END) as termines,
                COUNT(CASE WHEN statut = 'Complet' THEN 1 END) as complets,
                COUNT(CASE WHEN statut != 'Terminé' THEN 1 END) as incomplets,
                COUNT(CASE WHEN statut IN ('En cours', 'En attente') THEN 1 END) as ouverts,
                COUNT(CASE WHEN statut IN ('Accepté', 'Complet', 'Terminé', 'Visa obtenu') THEN 1 END) as acceptes,
                COUNT(CASE WHEN statut IN ('Refusé', 'Rejeté', 'Visa refusé') THEN 1 END) as refuses,
                COUNT(CASE WHEN statut IN ('En attente', 'En cours', 'En attente visa') THEN 1 END) as en_attente_decision,
                COUNT(CASE WHEN statut = 'Visa obtenu' THEN 1 END) as visas_obtenus,
                COUNT(CASE WHEN statut = 'Visa refusé' THEN 1 END) as visas_refuses,
                COUNT(CASE WHEN date_ouverture = ? THEN 1 END) as aujourdhui,
                COUNT(CASE WHEN date_ouverture >= ? AND date_ouverture <= ? THEN 1 END) as ce_mois
            ", [$today, $startOfMonth, $endOfMonth])
            ->first();

        // 2. Dossiers sans documents (requête séparée car jointure)
        $documentsManquants = DB::table('dossiers')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('documents')
                    ->whereRaw('documents.dossier_id = dossiers.id');
            })
            ->count();

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
            ->selectRaw("TO_CHAR(date_ouverture, 'YYYY-MM') as ym, COUNT(*) as total")
            ->where('date_ouverture', '>=', $sixMonthsAgo)
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        // 6. Tendances Revenus
        $revenusTrendRows = DB::table('payments')
            ->selectRaw("TO_CHAR(date_paiement, 'YYYY-MM') as ym, SUM(montant) as total")
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
            'dossiers_complets' => (int) $dossierStats->complets,
            'dossiers_termines' => (int) $dossierStats->termines,
            'dossiers_incomplets' => (int) $dossierStats->incomplets,
            'dossiers_ouverts' => (int) $dossierStats->ouverts,
            'dossiers_acceptes' => (int) $dossierStats->acceptes,
            'dossiers_refuses' => (int) $dossierStats->refuses,
            'dossiers_en_attente_decision' => (int) $dossierStats->en_attente_decision,
            'visas_obtenus' => (int) $dossierStats->visas_obtenus,
            'visas_refuses' => (int) $dossierStats->visas_refuses,
            'dossiers_aujourdhui' => (int) $dossierStats->aujourdhui,
            'dossiers_ce_mois' => (int) $dossierStats->ce_mois,
            'documents_manquants' => (int) $documentsManquants,
            'paiements_recents' => (int) $paymentStats->recents,
            'total_revenus' => (float) $paymentStats->total_revenus,
            'paiements_en_attente' => (int) $invoiceStats->en_attente,
            'pending_invoices' => (int) $invoiceStats->pending_count,
            'dossiers_par_statut' => $dossiersParStatut,
            'dossiers_trend_mois' => $dossiersTrendMois,
            'revenus_trend_mois' => $revenusTrendMois,
            'dossiers_par_destination' => $dossiersParDestination,
        ];
    }
}
