<x-pdf.document
    title="Liste des dossiers"
    :generated-at="$generatedAt"
    :company="$company ?? null"
    :filters="$filtres ?? []"
    :subtitle="'Export limité à 500 lignes'"
>
    <table class="export">
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
            @forelse($dossiers as $d)
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
            @empty
                <tr>
                    <td colspan="7">Aucun dossier à exporter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-pdf.document>
