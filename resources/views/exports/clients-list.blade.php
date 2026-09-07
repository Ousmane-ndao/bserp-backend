<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Export clients BSERP</title>
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
    <h1>Liste des clients</h1>
    @if($company)
        <p class="meta">{{ $company->company_name ?? 'BSERP' }}</p>
    @endif
    <p class="meta">Généré le {{ $generatedAt }} — {{ $clients->count() }} client(s)</p>
    @if(!empty(array_filter($filters ?? [])))
        <p class="meta">Filtres appliqués : {{ json_encode($filters, JSON_UNESCAPED_UNICODE) }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Destination</th>
                <th>Établissement</th>
                <th>Niveau d'étude</th>
                <th>Date d'ouverture</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clients as $client)
                <tr>
                    <td>{{ $client->prenom }}</td>
                    <td>{{ $client->nom }}</td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->telephone ?? '—' }}</td>
                    <td>{{ $client->destination?->name ?? '—' }}</td>
                    <td>{{ $client->etablissement ?? '—' }}</td>
                    <td>{{ $client->niveau_etude ?? '—' }}</td>
                    <td>{{ $client->date_ouverture ? \Illuminate\Support\Carbon::parse($client->date_ouverture)->format('d/m/Y') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Aucun client trouvé.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
