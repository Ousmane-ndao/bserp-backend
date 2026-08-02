<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\DossierExport;
use App\Models\Expense;
use App\Models\Payment;
use App\Jobs\GenerateDossiersExportJob;
use App\Services\PaymentService;
use App\Support\DossierListQuery;
use App\Support\DocumentsDisk;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    use DocumentsDisk;

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    private const DOSSIER_FILTER_KEYS = [
        'search',
        'statut',
        'destination_group',
        'date_ouverture_from',
        'date_ouverture_to',
        'sort_by',
        'sort_dir',
        'client_id',
    ];

    public function paymentsCsv(): StreamedResponse
    {
        return $this->paymentsExportCsv(request());
    }

    public function paymentsIndex(Request $request): JsonResponse
    {
        $filters = $this->paymentExportFilters($request);

        $payments = $this->paymentService
            ->paymentsForExport(
                $filters['destination_id'],
                $filters['date_from'],
                $filters['date_to'],
                $filters['statut'],
                $filters['client_id'],
                $filters['dossier_id'],
            )
            ->limit(5000)
            ->get();

        $summaryRows = $this->paymentService->buildDossierSummaryRows(
            $filters['destination_id'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['statut'],
            $filters['client_id'],
        );

        return response()->json([
            'data' => [
                'payments' => $payments->map(fn (Payment $payment) => [
                    'id' => (string) $payment->id,
                    'clientId' => (string) $payment->client_id,
                    'clientName' => trim(($payment->client?->prenom ?? '').' '.($payment->client?->nom ?? '')),
                    'dossierId' => $payment->dossier_id ? (string) $payment->dossier_id : null,
                    'dossierReference' => $payment->dossier?->reference,
                    'destination' => $payment->client?->destination?->name,
                    'avanceNumero' => $payment->avance_numero,
                    'amount' => (string) $payment->montant,
                    'currency' => $payment->currency ?? config('currency.code'),
                    'method' => $payment->methode,
                    'commentaire' => $payment->commentaire,
                    'paidAt' => $payment->date_paiement?->format('Y-m-d'),
                ])->values(),
                'summary' => $summaryRows,
            ],
            'meta' => [
                'filters' => $filters,
                'currency' => config('currency.code'),
            ],
        ]);
    }

    public function paymentsExportCsv(Request $request): StreamedResponse
    {
        $filters = $this->paymentExportFilters($request);
        $filename = 'paiements-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id', 'client', 'dossier', 'destination', 'acompte', 'montant', 'devise', 'methode', 'date_paiement', 'commentaire',
            ], ';');

            $this->paymentService
                ->paymentsForExport(
                    $filters['destination_id'],
                    $filters['date_from'],
                    $filters['date_to'],
                    $filters['statut'],
                    $filters['client_id'],
                    $filters['dossier_id'],
                )
                ->chunk(200, function ($chunk) use ($out): void {
                    foreach ($chunk as $payment) {
                        $client = $payment->client;
                        fputcsv($out, [
                            $payment->id,
                            $client ? trim($client->prenom.' '.$client->nom) : '',
                            $payment->dossier?->reference ?? '',
                            $client?->destination?->name ?? '',
                            $payment->avance_numero ?? '',
                            $payment->montant,
                            $payment->currency ?? config('currency.code'),
                            $payment->methode,
                            $payment->date_paiement?->format('d/m/Y'),
                            $payment->commentaire ?? '',
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function paymentsExportPdf(Request $request): Response
    {
        $filters = $this->paymentExportFilters($request);
        $company = CompanySetting::query()->first();

        $payments = $this->paymentService
            ->paymentsForExport(
                $filters['destination_id'],
                $filters['date_from'],
                $filters['date_to'],
                $filters['statut'],
                $filters['client_id'],
                $filters['dossier_id'],
            )
            ->limit(2000)
            ->get();

        $summaryRows = $this->paymentService->buildDossierSummaryRows(
            $filters['destination_id'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['statut'],
            $filters['client_id'],
        );

        $pdf = Pdf::loadView('exports.payments-report', [
            'company' => $company,
            'payments' => $payments,
            'summaryRows' => $summaryRows,
            'filters' => $filters,
            'currencyLabel' => config('currency.label'),
            'generatedAt' => now()->locale('fr')->isoFormat('LLL'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('rapport-paiements-'.now()->format('Y-m-d').'.pdf');
    }

    public function paymentsExportExcel(Request $request): StreamedResponse
    {
        $filters = $this->paymentExportFilters($request);
        $spreadsheet = new Spreadsheet;

        $acomptesSheet = $spreadsheet->getActiveSheet();
        $acomptesSheet->setTitle('Acomptes');
        $acomptesSheet->fromArray([
            ['ID', 'Client', 'Dossier', 'Destination', 'Acompte', 'Montant', 'Devise', 'Méthode', 'Date', 'Commentaire'],
        ]);

        $row = 2;
        $this->paymentService
            ->paymentsForExport(
                $filters['destination_id'],
                $filters['date_from'],
                $filters['date_to'],
                $filters['statut'],
                $filters['client_id'],
                $filters['dossier_id'],
            )
            ->chunk(200, function ($chunk) use ($acomptesSheet, &$row): void {
                foreach ($chunk as $payment) {
                    $client = $payment->client;
                    $acomptesSheet->fromArray([[
                        $payment->id,
                        $client ? trim($client->prenom.' '.$client->nom) : '',
                        $payment->dossier?->reference ?? '',
                        $client?->destination?->name ?? '',
                        $payment->avance_numero ?? '',
                        $payment->montant,
                        $payment->currency ?? config('currency.code'),
                        $payment->methode,
                        $payment->date_paiement?->format('d/m/Y'),
                        $payment->commentaire ?? '',
                    ]], null, 'A'.$row);
                    $row++;
                }
            });

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Synthèse');
        $summarySheet->fromArray([
            ['Dossier', 'Client', 'Destination', 'Montant total', 'Total payé', 'Solde restant', 'Statut'],
        ]);

        $summaryRow = 2;
        foreach ($this->paymentService->buildDossierSummaryRows(
            $filters['destination_id'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['statut'],
            $filters['client_id'],
        ) as $summary) {
            $summarySheet->fromArray([[
                $summary['dossier_reference'],
                $summary['client_name'],
                $summary['destination'],
                $summary['montant_total'],
                $summary['total_paye'],
                $summary['solde_restant'],
                $summary['statut'],
            ]], null, 'A'.$summaryRow);
            $summaryRow++;
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'paiements-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{
     *   destination_id: ?int,
     *   date_from: ?string,
     *   date_to: ?string,
     *   statut: ?string,
     *   client_id: ?int,
     *   dossier_id: ?int
     * }
     */
    private function paymentExportFilters(Request $request): array
    {
        return [
            'destination_id' => $request->filled('destination_id') ? $request->integer('destination_id') : null,
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->toString() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->toString() : null,
            'statut' => $request->filled('statut') ? $request->string('statut')->toString() : null,
            'client_id' => $request->filled('client_id') ? $request->integer('client_id') : null,
            'dossier_id' => $request->filled('dossier_id') ? $request->integer('dossier_id') : null,
        ];
    }

    public function expensesCsv(): StreamedResponse
    {
        $filename = 'depenses-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'libelle', 'montant', 'devise', 'categorie', 'date_depense'], ';');

            Expense::query()->orderByDesc('id')->chunk(200, function ($chunk) use ($out): void {
                foreach ($chunk as $e) {
                    fputcsv($out, [
                        $e->id,
                        $e->libelle,
                        $e->montant,
                        $e->currency ?? config('currency.code'),
                        $e->categorie,
                        $e->date_depense?->format('Y-m-d'),
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function accountingXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $paySheet = $spreadsheet->getActiveSheet();
        $paySheet->setTitle('Paiements');
        $paySheet->fromArray([
            ['ID', 'Client', 'Montant', 'Devise', 'Méthode', 'Date'],
        ]);
        $row = 2;
        Payment::query()->with('client')->orderByDesc('id')->chunk(200, function ($chunk) use ($paySheet, &$row): void {
            foreach ($chunk as $p) {
                $client = $p->client;
                $name = $client ? trim($client->prenom.' '.$client->nom) : '';
                $paySheet->fromArray([[
                    $p->id,
                    $name,
                    $p->montant,
                    $p->currency ?? config('currency.code'),
                    $p->methode,
                    $p->date_paiement?->format('Y-m-d'),
                ]], null, 'A'.$row);
                $row++;
            }
        });

        $expSheet = $spreadsheet->createSheet();
        $expSheet->setTitle('Dépenses');
        $expSheet->fromArray([
            ['ID', 'Libellé', 'Montant', 'Devise', 'Catégorie', 'Date'],
        ]);
        $row = 2;
        Expense::query()->orderByDesc('id')->chunk(200, function ($chunk) use ($expSheet, &$row): void {
            foreach ($chunk as $e) {
                $expSheet->fromArray([[
                    $e->id,
                    $e->libelle,
                    $e->montant,
                    $e->currency ?? config('currency.code'),
                    $e->categorie,
                    $e->date_depense?->format('Y-m-d'),
                ]], null, 'A'.$row);
                $row++;
            }
        });

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'comptabilite-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function accountingPdf(): Response
    {
        $totalPayments = (float) Payment::query()->sum('montant');
        $totalExpenses = (float) Expense::query()->sum('montant');
        $company = CompanySetting::query()->first();

        $pdf = Pdf::loadView('exports.accounting-summary', [
            'company' => $company,
            'totalPayments' => $totalPayments,
            'totalExpenses' => $totalExpenses,
            'net' => $totalPayments - $totalExpenses,
            'currencyLabel' => config('currency.label'),
            'generatedAt' => now()->locale('fr')->isoFormat('LLL'),
        ])->setPaper('a4');

        return $pdf->download('rapport-comptabilite-'.now()->format('Y-m-d').'.pdf');
    }

    public function dossiersCsv(Request $request): StreamedResponse
    {
        $filename = 'dossiers-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id', 'reference', 'client', 'email', 'telephone', 'destination', 'type', 'statut', 'date_ouverture', 'nb_documents',
            ], ';');

            DossierListQuery::filtered($request)
                ->orderBy('dossiers.id')
                ->with(['client.destination'])
                ->withCount('documents')
                ->chunk(500, function ($chunk) use ($out): void {
                    foreach ($chunk as $d) {
                        $c = $d->client;
                        fputcsv($out, [
                            $d->id,
                            $d->reference,
                            $c ? trim($c->prenom.' '.$c->nom) : '',
                            $c?->email ?? '',
                            $c?->telephone ?? '',
                            $c?->destination?->name ?? '',
                            $d->type ?? '',
                            $d->statut,
                            $d->date_ouverture?->format('Y-m-d'),
                            $d->documents_count,
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function dossiersXlsx(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dossiers');
        $sheet->fromArray([
            ['ID', 'Référence', 'Client', 'Email', 'Téléphone', 'Destination', 'Type', 'Statut', 'Date ouverture', 'Nb documents'],
        ]);

        $row = 2;

        DossierListQuery::filtered($request)
            ->orderBy('dossiers.id')
            ->with(['client.destination'])
            ->withCount('documents')
            ->chunk(500, function ($chunk) use ($sheet, &$row): void {
                foreach ($chunk as $d) {
                    $c = $d->client;
                    $sheet->fromArray([[
                        $d->id,
                        $d->reference,
                        $c ? trim($c->prenom.' '.$c->nom) : '',
                        $c?->email ?? '',
                        $c?->telephone ?? '',
                        $c?->destination?->name ?? '',
                        $d->type ?? '',
                        $d->statut,
                        $d->date_ouverture?->format('Y-m-d'),
                        $d->documents_count,
                    ]], null, 'A'.$row);
                    $row++;
                }
            });

        $filename = 'dossiers-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function dossiersPdf(Request $request): Response
    {
        $dossiers = collect();

        DossierListQuery::filtered($request)
            ->orderBy('dossiers.id')
            ->with(['client.destination'])
            ->withCount('documents')
            ->chunk(500, function ($chunk) use (&$dossiers) {
                foreach ($chunk as $d) {
                    $dossiers->push($d);
                    if ($dossiers->count() >= 500) {
                        return false;
                    }
                }
            });

        $company = CompanySetting::query()->first();

        $pdf = Pdf::loadView('exports.dossiers-list', [
            'company' => $company,
            'dossiers' => $dossiers,
            'generatedAt' => now()->locale('fr')->isoFormat('LLL'),
            'filtres' => $request->only([
                'search', 'statut', 'destination_group', 'date_ouverture_from', 'date_ouverture_to', 'sort_by', 'sort_dir',
            ]),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('dossiers-'.now()->format('Y-m-d-His').'.pdf');
    }

    public function dossiersQueue(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);

        $filters = array_filter(
            $request->only(self::DOSSIER_FILTER_KEYS),
            static fn ($v) => $v !== null && $v !== ''
        );

        $export = DossierExport::query()->create([
            'requested_by' => $request->user()?->id,
            'format' => $payload['format'],
            'filters' => $filters,
            'status' => DossierExport::STATUS_PENDING,
        ]);

        GenerateDossiersExportJob::dispatch($export->id);

        return response()->json([
            'data' => [
                'id' => (string) $export->id,
                'status' => $export->status,
                'format' => $export->format,
                'createdAt' => $export->created_at?->toIso8601String(),
            ],
        ], 202);
    }

    public function dossiersQueueStatus(DossierExport $dossierExport): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => (string) $dossierExport->id,
                'status' => $dossierExport->status,
                'format' => $dossierExport->format,
                'fileReady' => $dossierExport->status === DossierExport::STATUS_READY && $dossierExport->file_path !== null,
                'error' => $dossierExport->error_message,
                'createdAt' => $dossierExport->created_at?->toIso8601String(),
                'startedAt' => $dossierExport->started_at?->toIso8601String(),
                'finishedAt' => $dossierExport->finished_at?->toIso8601String(),
            ],
        ]);
    }

    public function dossiersQueueDownload(DossierExport $dossierExport): Response
    {
        if ($dossierExport->status !== DossierExport::STATUS_READY || ! $dossierExport->file_path) {
            abort(409, 'Export non prêt.');
        }

        $disk = $this->documentsDisk();
        if (! $disk->exists($dossierExport->file_path)) {
            abort(404, 'Fichier export introuvable.');
        }

        return $disk->download(
            $dossierExport->file_path,
            basename($dossierExport->file_path)
        );
    }
}
