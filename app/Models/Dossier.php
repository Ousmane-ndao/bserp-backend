<?php

namespace App\Models;

use App\Services\PaymentService;
use App\Support\DocumentCatalog;
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
        'montant_total',
        'solde_restant',
    ];

    protected function casts(): array
    {
        return [
            'date_ouverture' => 'date',
            'montant_total' => 'decimal:2',
            'solde_restant' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('dashboard_full_stats'));
        static::deleted(fn () => Cache::forget('dashboard_full_stats'));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function estComplet(): bool
    {
        $this->loadMissing('documents:id,dossier_id,type_document');
        $types = $this->documents
            ->map(fn (Document $document) => DocumentCatalog::normalizeType($document->type_document))
            ->unique()
            ->all();

        return count(array_intersect(DocumentCatalog::REQUIRED_TYPES, $types)) === count(DocumentCatalog::REQUIRED_TYPES);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function totalPaye(?Payment $exclude = null): float
    {
        $query = $this->payments();

        if ($exclude) {
            $query->where('id', '!=', $exclude->id);
        }

        return (float) $query->sum('montant');
    }

    public function recalculateSolde(?Payment $exclude = null): float
    {
        $solde = round((float) $this->montant_total - $this->totalPaye($exclude), 2);
        $this->forceFill(['solde_restant' => $solde])->saveQuietly();

        return $solde;
    }

    public function paymentStatus(?Payment $exclude = null): string
    {
        return app(PaymentService::class)->getDossierStatutPaiement($this, $exclude);
    }

    public function belongsToClient(int $clientId): bool
    {
        return (int) $this->client_id === $clientId;
    }
}
