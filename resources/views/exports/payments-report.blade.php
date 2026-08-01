<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rapport des paiements</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 24px; margin-bottom: 8px; }
        .muted { color: #555; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .right { text-align: right; }
        .filters { margin-top: 8px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <h1>Rapport des paiements / acomptes</h1>
    <p class="muted">{{ $company?->company_name ?? 'BS Consulting' }}</p>
    <p class="muted">Généré le {{ $generatedAt }}</p>

    <div class="filters">
        <p class="muted">
            Filtres :
            @if(!empty($filters['destination_id'])) Destination #{{ $filters['destination_id'] }} @endif
            @if(!empty($filters['client_id'])) | Client #{{ $filters['client_id'] }} @endif
            @if(!empty($filters['dossier_id'])) | Dossier #{{ $filters['dossier_id'] }} @endif
            @if(!empty($filters['date_from'])) | Du {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }} @endif
            @if(!empty($filters['date_to'])) | Au {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }} @endif
            @if(!empty($filters['statut'])) | Statut : {{ $filters['statut'] }} @endif
        </p>
    </div>

    <h2>Détail des acomptes</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Client</th>
                <th>Dossier</th>
                <th>Destination</th>
                <th>Acompte</th>
                <th class="right">Montant ({{ $currencyLabel }})</th>
                <th>Méthode</th>
                <th>Date</th>
                <th>Commentaire</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->id }}</td>
                    <td>{{ trim(($payment->client?->prenom ?? '').' '.($payment->client?->nom ?? '')) }}</td>
                    <td>{{ $payment->dossier?->reference ?? '—' }}</td>
                    <td>{{ $payment->client?->destination?->name ?? '—' }}</td>
                    <td>{{ $payment->avance_numero ?? '—' }}</td>
                    <td class="right">{{ number_format((float) $payment->montant, 0, ',', ' ') }}</td>
                    <td>{{ $payment->methode }}</td>
                    <td>{{ $payment->date_paiement?->format('d/m/Y') }}</td>
                    <td>{{ $payment->commentaire ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Aucun paiement trouvé pour ces filtres.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Synthèse par dossier</h2>
    <table>
        <thead>
            <tr>
                <th>Dossier</th>
                <th>Client</th>
                <th>Destination</th>
                <th class="right">Montant total</th>
                <th class="right">Total payé</th>
                <th class="right">Solde restant</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summaryRows as $row)
                <tr>
                    <td>{{ $row['dossier_reference'] }}</td>
                    <td>{{ $row['client_name'] }}</td>
                    <td>{{ $row['destination'] }}</td>
                    <td class="right">{{ number_format($row['montant_total'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($row['total_paye'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($row['solde_restant'], 0, ',', ' ') }}</td>
                    <td>{{ $row['statut'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Aucune synthèse disponible.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
