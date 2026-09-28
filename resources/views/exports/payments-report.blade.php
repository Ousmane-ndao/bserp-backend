<x-pdf.document
    title="Rapport des paiements / acomptes"
    :generated-at="$generatedAt"
    :company="$company ?? null"
    :filters="$filters ?? []"
>
    <h2>Détail des acomptes</h2>
    <table class="export">
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
                    <td colspan="9">Aucun paiement trouvé pour ces critères.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Synthèse par dossier</h2>
    <table class="export">
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
</x-pdf.document>
