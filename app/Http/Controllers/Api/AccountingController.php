<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AccountingController extends Controller
{
    public function summary(): JsonResponse
    {
        $currency = config('currency.code');

        $pendingInvoices = Invoice::query()
            ->where('statut', Invoice::STATUT_ENVOYEE)
            ->count();

        $paymentsByMethod = Payment::query()
            ->selectRaw('methode as method, SUM(montant) as total')
            ->groupBy('methode')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'total' => (float) $row->total,
            ]);

        $payments = Payment::query()->get(['montant', 'date_paiement']);
        $expenses = Expense::query()->get(['montant', 'date_depense']);

        $monthSet = [];
        foreach ($payments as $p) {
            if ($p->date_paiement) {
                $monthSet[$p->date_paiement->format('Y-m')] = true;
            }
        }
        foreach ($expenses as $e) {
            if ($e->date_depense) {
                $monthSet[$e->date_depense->format('Y-m')] = true;
            }
        }

        ksort($monthSet);
        $monthly = [];
        foreach (array_keys($monthSet) as $ym) {
            $carbon = Carbon::createFromFormat('Y-m', $ym)->locale('fr');
            $revenue = (float) $payments
                ->filter(fn ($p) => $p->date_paiement && $p->date_paiement->format('Y-m') === $ym)
                ->sum('montant');
            $expenseTotal = (float) $expenses
                ->filter(fn ($e) => $e->date_depense && $e->date_depense->format('Y-m') === $ym)
                ->sum('montant');
            $monthly[] = [
                'month' => $ym,
                'label' => ucfirst($carbon->translatedFormat('M Y')),
                'revenue' => $revenue,
                'expenses' => $expenseTotal,
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
