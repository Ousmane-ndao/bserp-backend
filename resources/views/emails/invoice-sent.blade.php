<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Facture {{ $invoice->numero }}</title>
</head>
<body style="font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; line-height: 1.5; max-width: 640px;">
    <p>{!! nl2br(e($bodyText)) !!}</p>
    <p style="margin-top: 1.5rem; font-size: 13px; color: #555;">La facture PDF est jointe à ce message.</p>
</body>
</html>
