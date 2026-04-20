<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Export dossiers BSERP</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 6px; }
        .meta { font-size: 8px; color: #444; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f4f8; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Liste des dossiers</h1>
    @if($company)
        <p class="meta">{{ $company->company_name ?? 'BSERP' }}</p>
    @endif
    <p class="meta">Généré le {{ $generatedAt }} — max. 500 lignes (export PDF)</p>
    @if(!empty(array_filter($filtres ?? [])))
        <p class="meta">Filtres : {{ json_encode($filtres, JSON_UNESCAPED_UNICODE) }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Réf.</th>
                <th>Client</th>
                <th>Destination</th>
                <th>Type</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Docs</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dossiers as $d)
                @php $c = $d->client; @endphp
                <tr>
                    <td>{{ $d->reference }}</td>
                    <td>{{ $c ? trim($c->prenom.' '.$c->nom) : '—' }}</td>
                    <td>{{ $c?->destination?->name ?? '—' }}</td>
                    <td>{{ $d->type ?? '—' }}</td>
                    <td>{{ $d->statut }}</td>
                    <td>{{ $d->date_ouverture?->format('d/m/Y') }}</td>
                    <td>{{ $d->documents_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
