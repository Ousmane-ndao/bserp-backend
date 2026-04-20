<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Dossier extends Model
{
    /** Statuts métier (liste fermée pour filtres / validation). */
    public const STATUTS = [
        'En cours',
        'Complet',
        'Terminé',
        'En attente',
        'Rejeté',
        'Accepté',
        'Refusé',
        'Visa obtenu',
        'Visa refusé',
        'En attente visa',
    ];

    protected $fillable = [
        'client_id',
        'reference',
        'type',
        'statut',
        'date_ouverture',
    ];

    protected function casts(): array
    {
        return [
            'date_ouverture' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('dashboard_stats'));
        static::deleted(fn () => Cache::forget('dashboard_stats'));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
