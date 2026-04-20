<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reçu {{ $invoice->numero }}</title>
    <style>
        @page { margin: 16px 16px 20px 16px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.7px; color: #1f2937; margin: 0; }
        .top-logo-wrap { text-align: left; margin-top: 2px; margin-bottom: 4px; }
        .top-logo-wrap .logo { margin-top: 0; }
        .row { width: 100%; }
        .left { float: left; width: 58%; }
        .right { float: right; width: 42%; text-align: right; }
        .clear { clear: both; }
        .title { font-size: 40px; font-weight: 700; letter-spacing: .2px; margin: 0; color: #111827; line-height: 1; }
        .meta { color: #6b7280; margin: 5px 0 0 0; font-size: 10.7px; }
        .strong { font-weight: 700; }
        .logo { width: 104px; height: auto; margin-top: 6px; }
        .mt-32 { margin-top: 38px; }
        .mt-10 { margin-top: 10px; }
        .party-left { float: left; width: 55%; }
        .party-right { float: right; width: 38%; text-align: left; }
        .party-line { margin: 2px 0; line-height: 1.35; }
        .intro { margin-top: 40px; line-height: 1.45; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        .items { margin-top: 18px; }
        .items th { background: #e5e7eb; color: #111827; font-weight: 700; text-align: left; padding: 6px 8px; border-top: 1px solid #d1d5db; border-bottom: 1px solid #d1d5db; font-size: 10.8px; }
        .items td { padding: 8px 8px; border-bottom: 1px solid #d1d5db; color: #1f2937; font-size: 10.8px; }
        .items .col-description { width: 30%; }
        .items .col-date { width: 15%; }
        .items .col-qte { width: 8%; }
        .items .col-unite { width: 10%; }
        .items .col-prix { width: 16%; }
        .items .col-tva { width: 8%; }
        .items .col-montant { width: 13%; }
        .num { text-align: right; }
        .center { text-align: center; }
        .totals-wrap { width: 31%; margin-left: auto; margin-top: 16px; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 2px 0; border: 0; }
        .totals .label { font-weight: 700; }
        .totals .grand td { border-top: 2px solid #111827; padding-top: 6px; font-weight: 700; font-size: 15px; line-height: 1.05; color: #111827; }
        .spacer-large { height: 300px; }
        .footer-note { text-align: center; color: #6b7280; font-size: 10.8px; font-style: italic; line-height: 1.45; }
        .company-footer { margin-top: 16px; text-align: center; font-size: 9.8px; color: #374151; line-height: 1.45; }
    </style>
</head>
<body>
    @php
        $logoCandidates = [
            public_path('brand-bs-consulting-dark.png'),
            public_path('brand-bs-consulting.png'),
            public_path('logo-bs-consulting.png'),
        ];
        $logoPath = null;
        foreach ($logoCandidates as $candidate) {
            if (file_exists($candidate)) {
                $logoPath = $candidate;
                break;
            }
        }
        $logoDataUri = null;
        if ($logoPath !== null) {
            $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $logoMime = $logoExt === 'jpg' || $logoExt === 'jpeg' ? 'image/jpeg' : 'image/png';
            $logoDataUri = 'data:'.$logoMime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }
        $clientName = trim(($invoice->client?->prenom ?? '').' '.($invoice->client?->nom ?? ''));
        $invoiceDate = $invoice->date_emission?->format('d.m.Y') ?? now()->format('d.m.Y');
        $invoiceDateTime = $invoice->date_emission?->setTimeFrom($invoice->created_at ?? now())->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i');
        $currency = 'FCFA';
        $tvaRate = 10.0;
        $totalTtc = round((float) $invoice->montant_ttc, 2);
        $totalHt = round($totalTtc / (1 + ($tvaRate / 100)), 2);
        $tvaAmount = round($totalTtc - $totalHt, 2);
        $amountPaid = $totalTtc;
        $percentage = $totalTtc > 0 ? round(($amountPaid / $totalTtc) * 100, 2) : 0;
        $destination = $invoice->client?->destination?->name ?? 'Européenne (France)';
        $clientCity = $invoice->client?->etablissement ?? 'Sangalkam';
        $clientLocation = $invoice->client?->destination?->name ?? 'RUFISQUE';
        $receiptCompanyName = $company?->company_name ?? 'BS CONSULTING voyage';
        $receiptManagerName = 'Mme BA (NGA BINTA MBAYE)';
        $receiptAddressLine1 = 'N108 Cité Emetteur Keur Massar';
        $receiptAddressLine2 = 'Villa N° 08';
        $receiptAddressLine3 = '28557 DAKAR';
        $receiptPhone = '+221 77 621 16 88';
        $receiptEmail = 'madamebacci@gmail.com';
        $receiptWebsite = 'www.bsconsultingvoyage.com';

        $formatCurrency = static fn (float $value): string => number_format($value, 0, ',', ' ').' '.$currency;
        $dynamicSummary = 'Le client a payé une avance de '.$formatCurrency($amountPaid)
            .' représentant environ '.str_replace('.', ',', (string) $percentage).'% pour un total à payer de '.$formatCurrency($totalTtc)
            .' TTC avec '.$formatCurrency($totalHt).' HT et '.$formatCurrency($tvaAmount)
            .' de TVA pour une destination '.$destination.' SOLDE.';
    @endphp

    <div class="top-logo-wrap">
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo BS Consulting" class="logo"/>
        @endif
    </div>

    <div class="row mt-10">
        <div class="left"></div>
        <div class="right">
            <p class="title">REÇU</p>
            <p class="meta">Pour facture numéro: {{ $invoice->numero }}</p>
            <p class="meta">Date: {{ $invoiceDate }}</p>
        </div>
    </div>
    <div class="clear"></div>

    <div class="row mt-32">
        <div class="party-left">
            <div class="strong party-line">{{ $receiptCompanyName }}</div>
            <div class="party-line">{{ $receiptManagerName }}</div>
            <div class="party-line">{{ $receiptAddressLine1 }}</div>
            <div class="party-line">{{ $receiptAddressLine2 }}</div>
            <div class="party-line">{{ $receiptAddressLine3 }}</div>
            <div class="party-line">{{ $receiptPhone }}</div>
            <div class="party-line">{{ $receiptEmail }}</div>
            <div class="party-line">{{ $receiptWebsite }}</div>
        </div>
        <div class="party-right">
            <div class="strong party-line">{{ $clientName !== '' ? $clientName : 'Client' }}</div>
            <div class="party-line">{{ $clientCity }}</div>
            <div class="party-line">{{ strtoupper((string) $clientLocation) }}</div>
        </div>
    </div>
    <div class="clear"></div>

    <div class="intro">
        {{ $invoice->notes ?: $dynamicSummary }}
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="col-description">Description</th>
                <th class="col-date">Date</th>
                <th class="center col-qte">Qté</th>
                <th class="center col-unite">Unité</th>
                <th class="col-prix">Prix unitaire</th>
                <th class="col-tva">TVA</th>
                <th class="num col-montant">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>CAMPUS France (AV) 2</td>
                <td>{{ $invoiceDateTime }}</td>
                <td class="center">1,00</td>
                <td class="center">pcs</td>
                <td>{{ $formatCurrency($totalHt) }}</td>
                <td>10,00 %</td>
                <td class="num">{{ $formatCurrency($totalTtc) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals">
            <tr>
                <td class="label">Total HT</td>
                <td class="num label">{{ $formatCurrency($totalHt) }}</td>
            </tr>
            <tr>
                <td class="label">TVA</td>
                <td class="num label">{{ $formatCurrency($tvaAmount) }}</td>
            </tr>
            <tr class="grand">
                <td>Total TTC</td>
                <td class="num">{{ $formatCurrency($totalTtc) }}</td>
            </tr>
        </table>
    </div>

    <div class="spacer-large"></div>
    <div class="footer-note">
        Informations additionnelles : Merci d’avoir choisi BS CONSULTING pour nos services.
        Le service après-vente prévoit le remboursement garanti à 50% après dossier infructueux.
    </div>
    <div class="company-footer">
        <strong>{{ $company?->company_name ?? 'BS CONSULTING voyage' }}</strong><br/>
        {{ $company?->address ?? 'N108 Cité Emetteur Keur Massar' }}<br/>
        Villa N° 08 28557 DAKAR<br/>
        Numéro de SIRET: 010736976 - Numéro de TVA: 678464
    </div>
</body>
</html>
