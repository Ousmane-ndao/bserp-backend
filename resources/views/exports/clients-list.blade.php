<x-pdf.document
    title="Liste des clients"
    :generated-at="$generatedAt"
    :company="$company ?? null"
    :filters="$filters ?? []"
    :subtitle="$clients->count().' client(s)'"
>
    <table class="export">
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
</x-pdf.document>
