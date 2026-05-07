<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ENVOYEE = 'envoyee';

    public const STATUT_PAYEE = 'payee';

    public const STATUT_ANNULEE = 'annulee';

    protected $fillable = [
        'client_id',
        'numero',
        'date_emission',
        'date_echeance',
        'statut',
        'montant_ttc',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'montant_ttc' => 'decimal:2',
            'date_emission' => 'date',
            'date_echeance' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if ($invoice->numero === null || $invoice->numero === '') {
                $invoice->numero = self::nextNumero();
            }
        });

        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
    }

    public static function nextNumero(): string
    {
        $year = (int) now()->format('Y');
        $prefix = 'F'.$year.'-';
        $last = self::query()
            ->where('numero', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('numero');
        $seq = 1;
        if ($last !== null && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->statut === self::STATUT_ENVOYEE;
    }
}
