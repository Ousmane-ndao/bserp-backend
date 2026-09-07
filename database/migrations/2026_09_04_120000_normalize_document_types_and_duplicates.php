<?php

use App\Models\Document;
use App\Support\DocumentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Collection;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        Document::query()->orderBy('id')->chunkById(500, function (Collection $documents) use (&$seen): void {
            foreach ($documents as $document) {
                $type = DocumentCatalog::normalizeType($document->type_document);
                if ($type !== $document->type_document) {
                    $document->forceFill(['type_document' => $type])->saveQuietly();
                }

                $name = trim((string) $document->original_filename);
                if ($name === '') {
                    continue;
                }

                $key = $document->dossier_id.'\0'.mb_strtolower($name).'\0'.$type;
                if (isset($seen[$key])) {
                    $document->delete();
                    continue;
                }

                $seen[$key] = true;
            }
        });
    }

    public function down(): void
    {
        // La normalisation et la suppression de doublons ne sont pas réversibles.
    }
};
