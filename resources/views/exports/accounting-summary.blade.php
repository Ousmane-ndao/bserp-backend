<x-pdf.document
    title="Résumé comptable"
    :generated-at="$generatedAt"
    :company="$company ?? null"
>
    <table class="export">
        <thead>
            <tr>
                <th>Indicateur</th>
                <th>Montant ({{ $currencyLabel }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total paiements (revenus)</td>
                <td>{{ number_format($totalPayments, 2, ',', ' ') }}</td>
            </tr>
            <tr>
                <td>Total dépenses</td>
                <td>{{ number_format($totalExpenses, 2, ',', ' ') }}</td>
            </tr>
            <tr>
                <td><strong>Bénéfice net</strong></td>
                <td><strong>{{ number_format($net, 2, ',', ' ') }}</strong></td>
            </tr>
        </tbody>
    </table>
</x-pdf.document>
