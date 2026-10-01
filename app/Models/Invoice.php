<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ENVOYEE = 'envoyee';

    public const STATUT_PAYEE = 'payee';

    public const STATUT_ANNULEE = 'annulee';

    protected $fillable = [
        'client_id',
        'creator_user_id',
        'creator_role',
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

        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats');
            \Illuminate\Support\Facades\Cache::forget('accounting_summary');
        });
        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats');
            \Illuminate\Support\Facades\Cache::forget('accounting_summary');
        });
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(InvoiceDispatch::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(InvoiceAuditLog::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->statut === self::STATUT_ENVOYEE;
    }
}
