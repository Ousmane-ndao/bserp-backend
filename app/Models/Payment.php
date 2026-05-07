<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'client_id',
        'montant',
        'currency',
        'methode',
        'date_paiement',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
