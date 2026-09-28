@props([
    'title',
    'generatedAt' => null,
    'company' => null,
    'filters' => [],
    'subtitle' => null,
])
@php
    $brand = \App\Support\PdfDocument::brand($company ?? null);
    $filterLines = \App\Support\PdfDocument::filterLines(is_array($filters) ? $filters : []);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }} — {{ $brand['name'] }}</title>
    <style>
        @page { margin: 16mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }
        .pdf-header { border-bottom: 3px solid #F4811F; padding-bottom: 10px; margin-bottom: 12px; }
        .pdf-header-table { width: 100%; border-collapse: collapse; }
        .pdf-header-table td { border: 0; vertical-align: middle; padding: 0; }
        .pdf-logo { height: 52px; width: auto; max-width: 240px; margin-bottom: 6px; }
        .pdf-company { font-size: 10px; font-weight: 700; color: #0b4f8a; margin-top: 2px; }
        .pdf-location { font-size: 8px; color: #6b7280; margin-top: 1px; }
        .pdf-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0; text-align: right; }
        .pdf-meta { font-size: 8px; color: #4b5563; margin: 4px 0 0; text-align: right; }
        .pdf-filters { margin: 0 0 12px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e5e7eb; }
        .pdf-filters-title { font-size: 8px; font-weight: 700; color: #0b4f8a; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 4px; }
        .pdf-filters p { margin: 0 0 2px; font-size: 8px; color: #374151; }
        table.export { width: 100%; border-collapse: collapse; }
        table.export th, table.export td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; }
        table.export th { background: #0b4f8a; color: #fff; font-weight: bold; }
        table.export tr:nth-child(even) { background: #f8fafc; }
        .right { text-align: right; }
        h2 { font-size: 12px; color: #0b4f8a; margin: 16px 0 8px; }
        .pdf-footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 7.5px; color: #6b7280; text-align: center; line-height: 1.45; }
    </style>
</head>
<body>
    <table class="pdf-header-table pdf-header">
        <tr>
            <td style="width: 58%;">
                @if($brand['logo'])
                    <img src="{{ $brand['logo'] }}" class="pdf-logo" alt="{{ $brand['name'] }}"/>
                @endif
                <div class="pdf-company">{{ $brand['name'] }}</div>
                @if($brand['location'])
                    <div class="pdf-location">{{ $brand['location'] }}</div>
                @endif
            </td>
            <td style="width: 42%;">
                <p class="pdf-title">{{ $title }}</p>
                @if($generatedAt)
                    <p class="pdf-meta">Généré le {{ $generatedAt }}</p>
                @endif
                @if($subtitle)
                    <p class="pdf-meta">{{ $subtitle }}</p>
                @endif
            </td>
        </tr>
    </table>

    @if(count($filterLines) > 0)
        <div class="pdf-filters">
            <p class="pdf-filters-title">Critères d’export</p>
            @foreach($filterLines as $line)
                <p>{{ $line }}</p>
            @endforeach
        </div>
    @endif

    {{ $slot }}

    <div class="pdf-footer">
        {{ $brand['name'] }}@if($brand['location']) — {{ $brand['location'] }}@endif
        <br/>Document généré automatiquement — usage interne — confidentiel
    </div>
</body>
</html>
