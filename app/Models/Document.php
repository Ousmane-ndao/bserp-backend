<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Document extends Model
{
    public const STATUTS = [
        'En attente',
        'Validé',
        'Refusé',
        'À remplacer',
    ];

    public static function hasStatutColumn(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn('documents', 'statut');
        }

        return $cached;
    }

    protected $fillable = [
        'client_id',
        'dossier_id',
        'type_document',
        'statut',
        'file_path',
        'original_filename',
        'size_bytes',
        'mime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }
}
