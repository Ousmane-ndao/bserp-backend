<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'libelle',
        'montant',
        'currency',
        'categorie',
        'date_depense',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_depense' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('accounting_summary'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('accounting_summary'));
    }
}
