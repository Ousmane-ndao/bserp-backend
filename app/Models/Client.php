<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $fillable = [
        'prenom',
        'nom',
        'date_naissance',
        'etablissement',
        'niveau_etude',
        'telephone',
        'email',
        'destination_id',
        'date_ouverture',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_ouverture' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
        static::deleted(fn () => \Illuminate\Support\Facades\Cache::forget('dashboard_full_stats'));
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function studentAccount(): HasOne
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function studentProgress(): HasOne
    {
        return $this->hasOne(StudentProgress::class);
    }

    public function dossiers(): HasMany
    {
        return $this->hasMany(Dossier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
