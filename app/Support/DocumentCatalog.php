<?php

namespace App\Support;

class DocumentCatalog
{
    /** @var list<string> */
    public const REQUIRED_TYPES = [
        'Photo',
        'CNI ou Passeport',
        "Certificat d'inscription",
        'Relevé de notes Bac',
    ];

    /** @var list<string> */
    public const OPTIONAL_TYPES = [
        'Bulletins de notes',
        'Diplôme Bac',
        'Travail',
        'CV',
    ];

    /** @return list<string> */
    public static function allTypes(): array
    {
        return array_values(array_unique(array_merge(self::REQUIRED_TYPES, self::OPTIONAL_TYPES)));
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
            $type = (string) $doc->type_document;
            if (! isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $doc;
        }

        $checklist = self::REQUIRED_TYPES;
        $checklist[] = 'Bulletins de notes';

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
