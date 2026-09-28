<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function summary(): JsonResponse
    {
        try {
            $payload = Cache::remember('accounting_summary', 120, function () {
                return $this->buildSummary();
            });
            if (! is_array($payload)) {
                Cache::forget('accounting_summary');
                $payload = $this->buildSummary();
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Accounting summary error: '.$e->getMessage());
            $message = config('app.debug') ? $e->getMessage() : 'Impossible de charger le résumé comptable.';

            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(): array
    {
        $currency = config('erp.currency_code', 'XOF');
        $monthExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(%s, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT(%s, '%%Y-%%m')",
            default => "strftime('%%Y-%%m', %s)",
        };

        $pendingInvoices = Invoice::query()
            ->where('statut', Invoice::STATUT_ENVOYEE)
            ->count();

        $paymentsByMethod = DB::table('payments')
            ->selectRaw('methode as method, SUM(montant) as total')
            ->groupBy('methode')
            ->orderByDesc('total')
            ->get();

        $revenues = DB::table('payments')
            ->selectRaw(sprintf($monthExpression, 'date_paiement').' as ym, SUM(montant) as total')
            ->whereNotNull('date_paiement')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $expenses = DB::table('expenses')
            ->selectRaw(sprintf($monthExpression, 'date_depense').' as ym, SUM(montant) as total')
            ->whereNotNull('date_depense')
            ->groupBy('ym')
            ->pluck('total', 'ym')
            ->all();

        $allMonths = array_unique(array_merge(array_keys($revenues), array_keys($expenses)));
        sort($allMonths);

        $monthly = [];
        foreach ($allMonths as $ym) {
            if (! is_string($ym) || ! preg_match('/^\d{4}-\d{2}$/', $ym)) {
                continue;
            }
            $carbon = Carbon::createFromFormat('Y-m', $ym)->locale('fr');
            $monthly[] = [
                'month' => $ym,
                'label' => ucfirst($carbon->translatedFormat('M Y')),
                'revenue' => (float) ($revenues[$ym] ?? 0),
                'expenses' => (float) ($expenses[$ym] ?? 0),
            ];
        }

        return [
            'currency' => $currency,
            'pending_invoices' => $pendingInvoices,
            'payments_by_method' => $paymentsByMethod,
            'monthly' => $monthly,
        ];
    }
}
