<?php

namespace App\Support;

use App\Models\Dossier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class DossierListQuery
{
    public const SORTABLE = ['reference', 'client_name', 'date_ouverture', 'statut', 'destination', 'id'];

    /**
     * Requête filtrée sans eager load ni tri (exports, chunk).
     *
     * @return Builder<Dossier>
     */
    public static function filtered(Request $request): Builder
    {
        $query = Dossier::query()->from('dossiers');

        if ($request->filled('client_id')) {
            $query->where('dossiers.client_id', $request->integer('client_id'));
        }

        if ($request->filled('search')) {
            $term = trim($request->string('search')->toString());
            if ($term !== '') {
                self::applySearch($query, $term);
            }
        }

        if ($request->filled('statut')) {
            $query->where('dossiers.statut', $request->string('statut')->toString());
        }

        if ($request->filled('destination_group')) {
            $group = strtolower($request->string('destination_group')->toString());
            if (in_array($group, ['france', 'canada', 'maroc', 'autres'], true)) {
                $query->whereHas('client', function ($cq) use ($group) {
                    $cq->whereHas('destination', function ($dq) use ($group) {
                        match ($group) {
                            'france' => $dq->whereRaw('LOWER(TRIM(name)) = ?', ['france']),
                            'canada' => $dq->whereRaw('LOWER(name) LIKE ?', ['%canada%']),
                            'maroc' => $dq->whereRaw('LOWER(name) LIKE ?', ['%maroc%']),
                            'autres' => $dq->whereRaw('LOWER(TRIM(name)) != ?', ['france'])
                                ->whereRaw('LOWER(name) NOT LIKE ?', ['%canada%'])
                                ->whereRaw('LOWER(name) NOT LIKE ?', ['%maroc%']),
                        };
                    });
                });
            }
        }

        if ($request->filled('date_ouverture_from')) {
            $query->whereDate('dossiers.date_ouverture', '>=', $request->string('date_ouverture_from')->toString());
        }
        if ($request->filled('date_ouverture_to')) {
            $query->whereDate('dossiers.date_ouverture', '<=', $request->string('date_ouverture_to')->toString());
        }

        return $query;
    }

    /**
     * Liste paginée : filtres + relations + comptages + tri.
     *
     * @return Builder<Dossier>
     */
    public static function base(Request $request): Builder
    {
        $query = self::filtered($request)
            ->with(['client.destination'])
            ->withCount('documents');

        self::applySort($query, $request);

        return $query;
    }

    /**
     * @param  Builder<Dossier>  $query
     */
    private static function applySearch(Builder $query, string $term): void
    {
        if (self::isExactReferenceLookup($term)) {
            $query->where('dossiers.reference', $term);

            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $mysql = $driver === 'mysql';
        $safeForFt = $mysql
            && strlen($term) >= 4
            && ! str_contains($term, '%')
            && ! str_contains($term, '_');
        $like = '%'.$term.'%';

        $query->where(function ($outer) use ($term, $like, $safeForFt) {
            $outer->where(function ($refQ) use ($term, $like, $safeForFt) {
                if ($safeForFt) {
                    $refQ->whereRaw('MATCH(dossiers.reference) AGAINST (? IN BOOLEAN MODE)', [$term.'*'])
                        ->orWhere('dossiers.reference', 'like', $like);
                } else {
                    $refQ->where('dossiers.reference', 'like', $like);
                }
            });

            $outer->orWhereIn('dossiers.client_id', function ($sub) use ($like) {
                $sub->select('id')
                    ->from('clients')
                    ->where(function ($c) use ($like) {
                        $c->where('nom', 'like', $like)
                            ->orWhere('prenom', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('telephone', 'like', $like);
                    });
            });
        });
    }

    private static function isExactReferenceLookup(string $term): bool
    {
        $t = trim($term);
        if ($t === '') {
            return false;
        }

        if (str_starts_with(strtoupper($t), 'REF-')) {
            return true;
        }

        return (bool) preg_match('/^D-\d{4}-\d+$/i', $t);
    }

    /**
     * @param  Builder<Dossier>  $query
     */
    private static function applySort(Builder $query, Request $request): void
    {
        $sortBy = $request->string('sort_by', 'reference')->toString();
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'reference';
        }
        $sortDir = strtolower($request->string('sort_dir', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'reference' => $query->orderBy('dossiers.reference', $sortDir)->select('dossiers.*'),
            'client_name' => $query
                ->leftJoin('clients as sort_clients', 'sort_clients.id', '=', 'dossiers.client_id')
                ->orderBy('sort_clients.nom', $sortDir)
                ->orderBy('sort_clients.prenom', $sortDir)
                ->select('dossiers.*'),
            'destination' => $query
                ->leftJoin('clients as sort_clients2', 'sort_clients2.id', '=', 'dossiers.client_id')
                ->leftJoin('destinations as sort_dest', 'sort_dest.id', '=', 'sort_clients2.destination_id')
                ->orderBy('sort_dest.name', $sortDir)
                ->select('dossiers.*'),
            'date_ouverture' => $query->orderBy('dossiers.date_ouverture', $sortDir)->select('dossiers.*'),
            'statut' => $query->orderBy('dossiers.statut', $sortDir)->select('dossiers.*'),
            default => $query->orderBy('dossiers.id', $sortDir)->select('dossiers.*'),
        };
    }
}
