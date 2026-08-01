<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Dossier;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public const STATUT_PAYE = 'Payé';

    public const STATUT_PARTIEL = 'Partiel';

    public const STATUT_EN_ATTENTE = 'En attente';

    public const STATUT_TROP_PERCU = 'Trop-perçu';

    public const DEFAULT_MONTANT_TOTAL = 272500.00;

    public function assertDossierBelongsToClient(Dossier $dossier, Client $client): void
    {
        if (! $dossier->belongsToClient($client->id)) {
            throw ValidationException::withMessages([
                'dossier' => ['Ce dossier n’appartient pas au client.'],
            ]);
        }
    }

    public function getDossierMontantTotal(Dossier $dossier): float
    {
        return (float) ($dossier->montant_total ?? self::DEFAULT_MONTANT_TOTAL);
    }

    public function getDossierTotalPaye(Dossier $dossier, ?Payment $exclude = null): float
    {
        return $dossier->totalPaye($exclude);
    }

    public function getDossierSoldeRestant(Dossier $dossier, ?Payment $exclude = null): float
    {
        return round($this->getDossierMontantTotal($dossier) - $this->getDossierTotalPaye($dossier, $exclude), 2);
    }

    public function getDossierStatutPaiement(Dossier $dossier, ?Payment $exclude = null): string
    {
        $totalPaye = $this->getDossierTotalPaye($dossier, $exclude);
        $solde = $this->getDossierSoldeRestant($dossier, $exclude);

        if ($totalPaye <= 0) {
            return self::STATUT_EN_ATTENTE;
        }

        if ($solde < 0) {
            return self::STATUT_TROP_PERCU;
        }

        if ($solde <= 0) {
            return self::STATUT_PAYE;
        }

        return self::STATUT_PARTIEL;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDossierSummary(Dossier $dossier): array
    {
        $dossier->loadMissing(['client.destination']);

        $montantTotal = $this->getDossierMontantTotal($dossier);
        $totalPaye = $this->getDossierTotalPaye($dossier);
        $soldeRestant = $this->getDossierSoldeRestant($dossier);

        return [
            'dossierId' => (string) $dossier->id,
            'dossierReference' => $dossier->reference,
            'clientId' => (string) $dossier->client_id,
            'clientName' => trim(($dossier->client?->prenom ?? '').' '.($dossier->client?->nom ?? '')),
            'destination' => $dossier->client?->destination?->name,
            'destinationId' => $dossier->client?->destination_id ? (string) $dossier->client->destination_id : null,
            'montantTotal' => number_format($montantTotal, 2, '.', ''),
            'totalPaye' => number_format($totalPaye, 2, '.', ''),
            'soldeRestant' => number_format($soldeRestant, 2, '.', ''),
            'statutPaiement' => $this->getDossierStatutPaiement($dossier),
            'currency' => config('currency.code'),
            'paymentsCount' => $dossier->payments()->count(),
        ];
    }

    public function nextAvanceNumeroForDossier(Dossier $dossier): string
    {
        $count = Payment::query()->where('dossier_id', $dossier->id)->count();

        return 'A'.($count + 1);
    }

    public function validateMontantForDossier(
        Dossier $dossier,
        float $montant,
        ?Payment $exclude = null,
        bool $allowOverpayment = false,
    ): void {
        if ($montant <= 0) {
            throw ValidationException::withMessages([
                'montant' => ['Le montant doit être supérieur à zéro.'],
            ]);
        }

        if ($allowOverpayment) {
            return;
        }

        $soldeRestant = $this->getDossierSoldeRestant($dossier, $exclude);

        if ($montant > $soldeRestant + 0.001) {
            throw ValidationException::withMessages([
                'montant' => [
                    sprintf(
                        'Le montant dépasse le solde restant (%s %s). Cochez « Autoriser trop-perçu » pour continuer.',
                        number_format(max(0, $soldeRestant), 0, ',', ' '),
                        config('currency.label'),
                    ),
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPaymentAtomic(array $data, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($data, $userId): Payment {
            $dossier = Dossier::query()->lockForUpdate()->findOrFail($data['dossier_id']);
            $montant = (float) $data['montant'];
            $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);

            $this->validateMontantForDossier($dossier, $montant, null, $allowOverpayment);

            $payment = Payment::query()->create([
                'client_id' => $dossier->client_id,
                'dossier_id' => $dossier->id,
                'montant' => $montant,
                'currency' => strtoupper(substr((string) ($data['currency'] ?? config('currency.code')), 0, 3)),
                'methode' => $data['methode'] ?? 'Virement',
                'commentaire' => $data['commentaire'] ?? null,
                'avance_numero' => $this->nextAvanceNumeroForDossier($dossier),
                'date_paiement' => $data['date_paiement'] ?? now()->toDateString(),
            ]);

            $dossier->recalculateSolde();

            $this->logAudit($payment, 'created', null, $this->snapshot($payment), $userId);

            return $payment->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePaymentAtomic(Payment $payment, array $data, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($payment, $data, $userId): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $before = $this->snapshot($payment);

            $dossierId = (int) ($data['dossier_id'] ?? $payment->dossier_id);
            $dossier = Dossier::query()->lockForUpdate()->findOrFail($dossierId);

            $montant = array_key_exists('montant', $data)
                ? (float) $data['montant']
                : (float) $payment->montant;

            $allowOverpayment = (bool) ($data['allow_overpayment'] ?? false);
            $this->validateMontantForDossier($dossier, $montant, $payment, $allowOverpayment);

            if (array_key_exists('client_id', $data)) {
                $payment->client_id = $data['client_id'];
            }
            if (array_key_exists('dossier_id', $data)) {
                $payment->dossier_id = $dossier->id;
            }
            if (array_key_exists('montant', $data)) {
                $payment->montant = $montant;
            }
            if (array_key_exists('methode', $data)) {
                $payment->methode = $data['methode'];
            }
            if (array_key_exists('commentaire', $data)) {
                $payment->commentaire = $data['commentaire'];
            }
            if (array_key_exists('date_paiement', $data)) {
                $payment->date_paiement = $data['date_paiement'];
            }
            if (array_key_exists('currency', $data) && $data['currency'] !== null) {
                $payment->currency = strtoupper(substr((string) $data['currency'], 0, 3));
            }

            $payment->save();

            $affectedDossierIds = array_unique(array_filter([
                $dossier->id,
                $before['dossier_id'] ?? null,
            ]));

            foreach ($affectedDossierIds as $affectedId) {
                $affectedDossier = Dossier::query()->lockForUpdate()->find($affectedId);
                $affectedDossier?->recalculateSolde();
            }

            $after = $this->snapshot($payment->fresh());
            $this->logAudit($payment, 'updated', $before, $after, $userId);

            return $payment->fresh();
        });
    }

    public function deletePaymentAtomic(Payment $payment, ?int $userId = null): void
    {
        DB::transaction(function () use ($payment, $userId): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $before = $this->snapshot($payment);
            $dossierId = $payment->dossier_id;

            $this->logAudit($payment, 'deleted', $before, null, $userId);
            $payment->delete();

            if ($dossierId) {
                $dossier = Dossier::query()->lockForUpdate()->find($dossierId);
                $dossier?->recalculateSolde();
            }
        });
    }

    public function initializeDossierAmounts(Dossier $dossier, ?float $montantTotal = null): void
    {
        $dossier->loadMissing('client.destination');

        $montant = $montantTotal ?? (float) ($dossier->client?->destination?->montant_total ?? self::DEFAULT_MONTANT_TOTAL);
        $totalPaye = $dossier->totalPaye();

        $dossier->forceFill([
            'montant_total' => $montant,
            'solde_restant' => round($montant - $totalPaye, 2),
        ])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function logAudit(
        Payment $payment,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): void {
        PaymentAuditLog::query()->create([
            'payment_id' => $payment->id,
            'client_id' => $payment->client_id,
            'user_id' => $userId,
            'action' => $action,
            'payload' => [
                'before' => $old,
                'after' => $new,
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Payment $payment): array
    {
        return [
            'montant' => (string) $payment->montant,
            'methode' => $payment->methode,
            'date_paiement' => $payment->date_paiement?->format('Y-m-d'),
            'commentaire' => $payment->commentaire,
            'avance_numero' => $payment->avance_numero,
            'dossier_id' => $payment->dossier_id,
        ];
    }

    /**
     * @return Builder<Payment>
     */
    public function paymentsForExport(
        ?int $destinationId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $statutPaiement = null,
        ?int $clientId = null,
        ?int $dossierId = null,
    ): Builder {
        $query = Payment::query()
            ->with([
                'client:id,prenom,nom,destination_id',
                'client.destination:id,name',
                'dossier:id,reference,montant_total,solde_restant,client_id',
            ])
            ->whereNotNull('dossier_id');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($dossierId) {
            $query->where('dossier_id', $dossierId);
        }

        if ($destinationId) {
            $query->whereHas('client', fn ($q) => $q->where('destination_id', $destinationId));
        }

        if ($dateFrom) {
            $query->whereDate('date_paiement', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date_paiement', '<=', $dateTo);
        }

        if ($statutPaiement) {
            $query->whereHas('dossier', function ($dossierQuery) use ($statutPaiement): void {
                $dossierQuery->whereRaw($this->dossierStatutSqlExpression().' = ?', [$statutPaiement]);
            });
        }

        return $query->orderBy('date_paiement')->orderBy('id');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildDossierSummaryRows(
        ?int $destinationId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $statutPaiement = null,
        ?int $clientId = null,
    ): Collection {
        $query = Dossier::query()
            ->with(['client.destination'])
            ->withSum('payments as total_paye', 'montant');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($destinationId) {
            $query->whereHas('client', fn ($q) => $q->where('destination_id', $destinationId));
        }

        if ($dateFrom || $dateTo) {
            $query->whereHas('payments', function ($paymentQuery) use ($dateFrom, $dateTo): void {
                if ($dateFrom) {
                    $paymentQuery->whereDate('date_paiement', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $paymentQuery->whereDate('date_paiement', '<=', $dateTo);
                }
            });
        }

        return $query->orderBy('id')->get()->map(function (Dossier $dossier) use ($statutPaiement): ?array {
            $summary = $this->getDossierSummary($dossier);

            if ($statutPaiement && $summary['statutPaiement'] !== $statutPaiement) {
                return null;
            }

            return [
                'dossier_id' => $dossier->id,
                'dossier_reference' => $dossier->reference,
                'client_id' => $dossier->client_id,
                'client_name' => $summary['clientName'],
                'destination' => $summary['destination'] ?? '',
                'montant_total' => (float) $summary['montantTotal'],
                'total_paye' => (float) $summary['totalPaye'],
                'solde_restant' => (float) $summary['soldeRestant'],
                'statut' => $summary['statutPaiement'],
            ];
        })->filter()->values();
    }

    private function dossierStatutSqlExpression(): string
    {
        $paye = self::STATUT_PAYE;
        $partiel = self::STATUT_PARTIEL;
        $attente = self::STATUT_EN_ATTENTE;
        $trop = self::STATUT_TROP_PERCU;

        return <<<SQL
CASE
    WHEN COALESCE((SELECT SUM(montant) FROM payments WHERE payments.dossier_id = dossiers.id), 0) <= 0 THEN '{$attente}'
    WHEN (dossiers.montant_total - COALESCE((SELECT SUM(montant) FROM payments WHERE payments.dossier_id = dossiers.id), 0)) < 0 THEN '{$trop}'
    WHEN (dossiers.montant_total - COALESCE((SELECT SUM(montant) FROM payments WHERE payments.dossier_id = dossiers.id), 0)) <= 0 THEN '{$paye}'
    ELSE '{$partiel}'
END
SQL;
    }
}
