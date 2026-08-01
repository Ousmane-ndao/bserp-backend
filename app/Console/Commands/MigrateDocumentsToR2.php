<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateDocumentsToR2 extends Command
{
    protected $signature = 'migrate:documents-to-r2
        {--chunk=100 : Number of files to copy per batch}
        {--dry-run : Simulate the migration without writing to R2}
        {--skip-existing : Skip files already present in R2}
        {--path=documents : Base path under the local disk to migrate}
    ';

    protected $description = 'Recursively copy local documents to Cloudflare R2 without deleting local files.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');
        $pathPrefix = trim($this->option('path') ?: 'documents', '/');

        $sourceDisk = Storage::disk('local');
        $targetDisk = Storage::disk('r2');

        if (! $sourceDisk->exists($pathPrefix)) {
            $this->error("Le chemin local '{$pathPrefix}' est introuvable sur le disque 'local'.");
            return 1;
        }

        $files = $sourceDisk->allFiles($pathPrefix);
        if (empty($files)) {
            $this->info("Aucun fichier trouvé dans le chemin local '{$pathPrefix}'.");
            return 0;
        }

        $total = count($files);
        $this->info("Migration de {$total} fichier(s) vers R2 à partir de '{$pathPrefix}'.");

        $results = [
            'total' => $total,
            'processed' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach (array_chunk($files, $chunkSize) as $chunk) {
            foreach ($chunk as $sourcePath) {
                $results['processed']++;
                $sourcePath = ltrim($sourcePath, '/');
                $destinationPath = $sourcePath;

                try {
                    if (! $sourceDisk->exists($sourcePath)) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'source_path' => $sourcePath,
                            'error' => 'Fichier local introuvable',
                        ];
                        $bar->advance();
                        continue;
                    }

                    if ($skipExisting && $targetDisk->exists($destinationPath)) {
                        $results['skipped']++;
                        $bar->advance();
                        continue;
                    }

                    if ($dryRun) {
                        $results['migrated']++;
                        $bar->advance();
                        continue;
                    }

                    $stream = $sourceDisk->readStream($sourcePath);
                    if ($stream === false) {
                        throw new \RuntimeException('Impossible de lire le fichier local.');
                    }

                    $targetDisk->put($destinationPath, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $results['migrated']++;
                } catch (\Throwable $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'source_path' => $sourcePath,
                        'destination_path' => $destinationPath,
                        'error' => $e->getMessage(),
                    ];
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $report = [
            'success' => $results['failed'] === 0,
            'results' => $results,
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $results['failed'] === 0 ? 0 : 1;
    }
}
