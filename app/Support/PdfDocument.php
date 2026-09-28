<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Destination;
use App\Models\Dossier;

class PdfDocument
{
    public const FALLBACK_NAME = 'BS Consulting';

    /**
     * @return array{name: string, address: ?string, city: ?string, country: ?string, location: ?string, logo: ?string}
     */
    public static function brand(?CompanySetting $company = null): array
    {
        $raw = trim((string) ($company?->company_name ?? ''));
        $name = ($raw === '' || strcasecmp($raw, 'BSERP') === 0)
            ? self::FALLBACK_NAME
            : $raw;

        $addressParts = array_filter([
            $company?->address,
            $company?->city,
            $company?->country,
        ], static fn ($v) => is_string($v) && trim($v) !== '');

        return [
            'name' => $name,
            'address' => $company?->address ? trim((string) $company->address) : null,
            'city' => $company?->city ? trim((string) $company->city) : null,
            'country' => $company?->country ? trim((string) $company->country) : null,
            'location' => $addressParts === [] ? null : implode(' · ', $addressParts),
            'logo' => self::logoDataUri(),
        ];
    }

    /** Chemin unique du logo officiel PNG (sans variante). */
    public static function officialLogoPath(): ?string
    {
        $candidates = [
            resource_path('brand/bs-consulting-logo.png'),
            public_path('brand/bs-consulting-logo.png'),
            public_path('bs-consulting-logo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function logoDataUri(): ?string
    {
        $path = self::officialLogoPath();
        if ($path === null) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Convertit les paramètres d’export en lignes lisibles (jamais de JSON brut).
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    public static function filterLines(array $filters): array
    {
        $skip = [
            'page', 'per_page', 'limit', 'offset', 'format', 'token',
            'sort_by', 'sort_dir',
        ];

        $labels = [
            'search' => 'Recherche',
            'statut' => 'Statut',
            'destination_id' => 'Destination',
            'destination_group' => 'Destination',
            'date_ouverture_from' => 'Ouverture du',
            'date_ouverture_to' => 'Ouverture au',
            'date_from' => 'Période du',
            'date_to' => 'Période au',
            'client_id' => 'Client',
            'dossier_id' => 'Dossier',
            'type_document' => 'Catégorie',
        ];

        $lines = [];

        $sortBy = self::scalar($filters['sort_by'] ?? null);
        $sortDir = strtolower(self::scalar($filters['sort_dir'] ?? ''));
        if ($sortBy !== '') {
            $sortLabels = [
                'reference' => 'Référence',
                'date_ouverture' => 'Date d’ouverture',
                'statut' => 'Statut',
                'client' => 'Client',
                'created_at' => 'Date de création',
            ];
            $dir = $sortDir === 'asc' ? 'croissant' : 'décroissant';
            $lines[] = 'Tri : '.($sortLabels[$sortBy] ?? $sortBy).' ('.$dir.')';
        }

        foreach ($filters as $key => $value) {
            if (! is_string($key) || in_array($key, $skip, true)) {
                continue;
            }
            $text = self::displayValue($key, $value);
            if ($text === '') {
                continue;
            }
            $label = $labels[$key] ?? str_replace('_', ' ', ucfirst($key));
            $lines[] = $label.' : '.$text;
        }

        return $lines;
    }

    private static function displayValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $parts = array_filter(array_map(static fn ($v) => self::scalar($v), $value));

            return implode(', ', $parts);
        }

        $text = self::scalar($value);
        if ($text === '') {
            return '';
        }

        if (in_array($key, ['date_from', 'date_to', 'date_ouverture_from', 'date_ouverture_to'], true)) {
            try {
                return \Carbon\Carbon::parse($text)->format('d/m/Y');
            } catch (\Throwable) {
                return $text;
            }
        }

        if ($key === 'destination_id' && ctype_digit($text)) {
            $name = Destination::query()->find((int) $text)?->name;

            return $name ?: $text;
        }

        if ($key === 'client_id' && ctype_digit($text)) {
            $client = Client::query()->find((int) $text);
            if ($client) {
                return trim($client->prenom.' '.$client->nom) ?: $text;
            }
        }

        if ($key === 'dossier_id' && ctype_digit($text)) {
            $ref = Dossier::query()->find((int) $text)?->reference;

            return $ref ?: $text;
        }

        return $text;
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
