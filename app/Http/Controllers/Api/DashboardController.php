<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const CACHE_KEY = 'dashboard_stats';

    private const CACHE_TTL = 300;

    public function __invoke(): JsonResponse
    {
        $totalClients = Client::query()->count();

        $cachedDossiers = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return [
                'total_dossiers' => Dossier::query()->count(),
                'dossiers_en_cours' => Dossier::query()->where('statut', 'En cours')->count(),
                'dossiers_termines' => Dossier::query()->where('statut', 'Terminé')->count(),
                'dossiers_incomplets' => Dossier::query()->where('statut', '!=', 'Terminé')->count(),
            ];
        });

        $dossiersComplets = Dossier::query()->where('statut', 'Complet')->count();

        $today = now()->toDateString();
        $dossiersAujourdhui = Dossier::query()
            ->whereDate('date_ouverture', $today)
            ->count();

        $dossiersCeMois = Dossier::query()
            ->whereYear('date_ouverture', (int) now()->format('Y'))
            ->whereMonth('date_ouverture', (int) now()->format('n'))
            ->count();

        $documentsManquants = Dossier::query()
            ->whereDoesntHave('documents')
            ->count();

        $paiementsRecents = Payment::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $totalRevenus = (float) Payment::query()->sum('montant');

        $paiementsEnAttente = Invoice::query()
            ->whereIn('statut', [Invoice::STATUT_BROUILLON, Invoice::STATUT_ENVOYEE])
            ->count();

        $pendingInvoices = Invoice::query()
            ->where('statut', Invoice::STATUT_ENVOYEE)
            ->count();

        $dossiersEnCoursOuAttente = Dossier::query()->whereIn('statut', ['En cours', 'En attente'])->count();
        $dossiersAcceptes = Dossier::query()->whereIn('statut', ['Accepté', 'Complet', 'Terminé', 'Visa obtenu'])->count();
        $dossiersRefuses = Dossier::query()->whereIn('statut', ['Refusé', 'Rejeté', 'Visa refusé'])->count();
        $dossiersEnAttenteDecision = Dossier::query()->whereIn('statut', ['En attente', 'En cours', 'En attente visa'])->count();
        $visasObtenus = Dossier::query()->where('statut', 'Visa obtenu')->count();
        $visasRefuses = Dossier::query()->where('statut', 'Visa refusé')->count();

        $dossiersParStatut = Dossier::query()
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->all();

        $dossiersTrendMois = [];
        $revenusTrendByKey = [];
        $revenusTrendRows = Payment::query()
            ->selectRaw("DATE_FORMAT(date_paiement, '%Y-%m') as ym, SUM(montant) as total")
            ->whereNotNull('date_paiement')
            ->where('date_paiement', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('ym')
            ->get();
        foreach ($revenusTrendRows as $row) {
            $revenusTrendByKey[(string) $row->ym] = (float) $row->total;
        }

        $revenusTrendMois = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $key = $d->format('Y-m');
            $dossiersTrendMois[] = [
                'key' => $key,
                'label' => $d->locale('fr')->isoFormat('MMM YY'),
                'total' => (int) Dossier::query()
                    ->whereYear('date_ouverture', (int) $d->format('Y'))
                    ->whereMonth('date_ouverture', (int) $d->format('n'))
                    ->count(),
            ];
            $revenusTrendMois[] = [
                'key' => $key,
                'label' => $d->locale('fr')->isoFormat('MMM YY'),
                'total' => (float) ($revenusTrendByKey[$key] ?? 0),
            ];
        }

        $dossiersParDestinationRows = Dossier::query()
            ->selectRaw('destinations.name as destination_name, COUNT(dossiers.id) as total')
            ->join('clients', 'clients.id', '=', 'dossiers.client_id')
            ->join('destinations', 'destinations.id', '=', 'clients.destination_id')
            ->groupBy('destinations.name')
            ->orderByDesc('total')
            ->get();

        $dossiersParDestination = $dossiersParDestinationRows->map(fn ($r) => [
            'name' => (string) $r->destination_name,
            'value' => (int) $r->total,
        ])->values()->all();

        return response()->json([
            'total_clients' => $totalClients,
            'total_dossiers' => $cachedDossiers['total_dossiers'],
            'dossiers_en_cours' => $cachedDossiers['dossiers_en_cours'],
            'dossiers_complets' => $dossiersComplets,
            'dossiers_termines' => $cachedDossiers['dossiers_termines'],
            'dossiers_incomplets' => $cachedDossiers['dossiers_incomplets'],
            'dossiers_ouverts' => $dossiersEnCoursOuAttente,
            'dossiers_acceptes' => $dossiersAcceptes,
            'dossiers_refuses' => $dossiersRefuses,
            'dossiers_en_attente_decision' => $dossiersEnAttenteDecision,
            'visas_obtenus' => $visasObtenus,
            'visas_refuses' => $visasRefuses,
            'dossiers_aujourdhui' => $dossiersAujourdhui,
            'dossiers_ce_mois' => $dossiersCeMois,
            'documents_manquants' => $documentsManquants,
            'paiements_recents' => $paiementsRecents,
            'total_revenus' => $totalRevenus,
            'paiements_en_attente' => $paiementsEnAttente,
            'pending_invoices' => $pendingInvoices,
            'dossiers_par_statut' => $dossiersParStatut,
            'dossiers_trend_mois' => $dossiersTrendMois,
            'revenus_trend_mois' => $revenusTrendMois,
            'dossiers_par_destination' => $dossiersParDestination,
        ]);
    }
}
