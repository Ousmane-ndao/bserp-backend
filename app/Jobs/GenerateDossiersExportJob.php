<?php

namespace App\Jobs;

use App\Models\CompanySetting;
use App\Models\DossierExport;
use App\Support\DossierListQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class GenerateDossiersExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = DossierExport::query()->find($this->exportId);
        if (! $export) {
            return;
        }

        $export->update([
            'status' => DossierExport::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $filters = is_array($export->filters) ? $export->filters : [];
            $request = new Request($filters);

            $path = match ($export->format) {
                'csv' => $this->makeCsv($request),
                'xlsx' => $this->makeXlsx($request),
                'pdf' => $this->makePdf($request),
                default => throw new \RuntimeException('Format export non supporté.'),
            };

            $export->update([
                'status' => DossierExport::STATUS_READY,
                'file_path' => $path,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $export->update([
                'status' => DossierExport::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);
        }
    }

    private function makeCsv(Request $request): string
    {
        $tmp = tmpfile();
        if (! $tmp) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire CSV.');
        }

        fwrite($tmp, "\xEF\xBB\xBF");
        fputcsv($tmp, [
            'id', 'reference', 'client', 'email', 'telephone', 'destination', 'type', 'statut', 'date_ouverture', 'nb_documents',
        ], ';');

        DossierListQuery::filtered($request)
            ->orderBy('dossiers.id')
            ->with(['client.destination'])
            ->withCount('documents')
            ->chunk(500, function ($chunk) use ($tmp): void {
                foreach ($chunk as $d) {
                    $c = $d->client;
                    fputcsv($tmp, [
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

        $meta = stream_get_meta_data($tmp);
        $tmpPath = $meta['uri'] ?? null;
        if (! is_string($tmpPath) || $tmpPath === '') {
            fclose($tmp);
            throw new \RuntimeException('Fichier temporaire CSV invalide.');
        }

        $path = 'exports/dossiers/dossiers-'.now()->format('Y-m-d-His').'-'.$this->exportId.'.csv';
        rewind($tmp);
        Storage::disk('local')->put($path, stream_get_contents($tmp) ?: '');
        fclose($tmp);

        return $path;
    }

    private function makeXlsx(Request $request): string
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

        $tmpPath = tempnam(sys_get_temp_dir(), 'bserp_xlsx_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire XLSX.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        $path = 'exports/dossiers/dossiers-'.now()->format('Y-m-d-His').'-'.$this->exportId.'.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmpPath) ?: '');
        @unlink($tmpPath);

        return $path;
    }

    private function makePdf(Request $request): string
    {
        $dossiers = new Collection;

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
            'filtres' => $request->all(),
        ])->setPaper('a4', 'landscape');

        $path = 'exports/dossiers/dossiers-'.now()->format('Y-m-d-His').'-'.$this->exportId.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
