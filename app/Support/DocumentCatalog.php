<?php

namespace App\Support;

class DocumentCatalog
{
    /** @var list<string> */
    public const REQUIRED_TYPES = [
        'CNI ou Passeport',
        'Bulletins de notes',
        'Diplôme Bac',
        "Certificat d'inscription",
        'Relevé du Bac',
        'Travaux',
        'Photo',
        'CV',
    ];

    /** @var array<string, string> */
    private const TYPE_ALIASES = [
        'Photo d’identité' => 'Photo',
        "Photo d'identité" => 'Photo',
        'Certificat de scolarité' => "Certificat d'inscription",
        'Certificat d’inscription' => "Certificat d'inscription",
        'Relevé de notes Bac' => 'Relevé du Bac',
        'Travail' => 'Travaux',
    ];

    public static function normalizeType(?string $type): string
    {
        $value = trim((string) $type);

        return self::TYPE_ALIASES[$value] ?? $value;
    }

    /** @return list<string> */
    public static function allTypes(): array
    {
        return self::REQUIRED_TYPES;
    }

    /**
     * @param  iterable<\App\Models\Document>  $documents
     * @return array{
     *   progressPercent: int,
     *   presentCount: int,
     *   totalRequired: int,
     *   missingTypes: list<string>,
     *   categories: list<array{type: string, present: bool, count: int, validated: int, pending: int, refused: int}>
     * }
     */
    public static function summarizeForClient(iterable $documents): array
    {
        $grouped = [];
        foreach ($documents as $doc) {
            $type = self::normalizeType($doc->type_document);
            if (! isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $doc;
        }

        $checklist = self::REQUIRED_TYPES;

        $presentCount = 0;
        $missingTypes = [];
        $categories = [];

        foreach ($checklist as $type) {
            $items = $grouped[$type] ?? [];
            $count = count($items);
            $present = $count > 0;
            if ($present) {
                $presentCount++;
            } else {
                $missingTypes[] = $type;
            }

            $validated = 0;
            $pending = 0;
            $refused = 0;
            foreach ($items as $item) {
                $statut = (string) ($item->statut ?? 'En attente');
                if ($statut === 'Validé') {
                    $validated++;
                } elseif ($statut === 'Refusé' || $statut === 'À remplacer') {
                    $refused++;
                } else {
                    $pending++;
                }
            }

            $categories[] = [
                'type' => $type,
                'present' => $present,
                'count' => $count,
                'validated' => $validated,
                'pending' => $pending,
                'refused' => $refused,
            ];
        }

        $totalRequired = count($checklist);
        $progressPercent = $totalRequired > 0
            ? (int) round(($presentCount / $totalRequired) * 100)
            : 0;

        return [
            'progressPercent' => $progressPercent,
            'presentCount' => $presentCount,
            'totalRequired' => $totalRequired,
            'missingTypes' => $missingTypes,
            'categories' => $categories,
        ];
    }
}
