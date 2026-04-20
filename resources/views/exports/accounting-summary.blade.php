<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rapport comptabilité</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #555; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Résumé comptable</h1>
    <p class="muted">{{ $company?->company_name ?? 'BSERP' }}</p>
    <p class="muted">Généré le {{ $generatedAt }}</p>

    <table>
        <tr><th>Indicateur</th><th>Montant ({{ $currencyLabel }})</th></tr>
        <tr><td>Total paiements (revenus)</td><td>{{ number_format($totalPayments, 2, ',', ' ') }}</td></tr>
        <tr><td>Total dépenses</td><td>{{ number_format($totalExpenses, 2, ',', ' ') }}</td></tr>
        <tr><td><strong>Bénéfice net</strong></td><td><strong>{{ number_format($net, 2, ',', ' ') }}</strong></td></tr>
    </table>
</body>
</html>
