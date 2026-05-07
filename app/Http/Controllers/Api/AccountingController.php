<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function summary(): JsonResponse
    {
        $currency = config('erp.currency_code', 'XOF');

        $pendingInvoices = Invoice::query()
            ->where('statut', Invoice::STATUT_ENVOYEE)
            ->count();

        $paymentsByMethod = DB::table('payments')
            ->selectRaw('methode as method, SUM(montant) as total')
            ->groupBy('methode')
            ->orderByDesc('total')
            ->get();

        $revenues = DB::table('payments')
            ->selectRaw("TO_CHAR(date_paiement, 'YYYY-MM') as ym, SUM(montant) as total")
            ->whereNotNull('date_paiement')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $expenses = DB::table('expenses')
            ->selectRaw("TO_CHAR(date_depense, 'YYYY-MM') as ym, SUM(montant) as total")
            ->whereNotNull('date_depense')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $allMonths = array_unique(array_merge(array_keys($revenues), array_keys($expenses)));
        sort($allMonths);

        $monthly = [];
        foreach ($allMonths as $ym) {
            $carbon = Carbon::createFromFormat('Y-m', $ym)->locale('fr');
            $monthly[] = [
                'month' => $ym,
                'label' => ucfirst($carbon->translatedFormat('M Y')),
                'revenue' => (float) ($revenues[$ym] ?? 0),
                'expenses' => (float) ($expenses[$ym] ?? 0),
            ];
        }

        return response()->json([
            'currency' => $currency,
            'pending_invoices' => $pendingInvoices,
            'payments_by_method' => $paymentsByMethod,
            'monthly' => $monthly,
        ]);
    }
}
