<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDossierRequest;
use App\Http\Requests\UpdateDossierRequest;
use App\Http\Resources\DossierResource;
use App\Models\Dossier;
use App\Support\DossierListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DossierController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $rows = DossierListQuery::filtered($request)
            ->join('clients', 'clients.id', '=', 'dossiers.client_id')
            ->select(['dossiers.id', 'dossiers.reference', 'clients.prenom', 'clients.nom'])
            ->orderByDesc('dossiers.id')
            ->limit(50)
            ->get();

        $data = $rows->map(fn ($r) => [
            'id' => (string) $r->id,
            'reference' => $r->reference,
            'client' => trim(($r->prenom ?? '').' '.($r->nom ?? '')),
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 500);

        if ($request->boolean('cursor_mode')) {
            $cursor = $request->query('cursor');
            $paginator = DossierListQuery::base($request)
                ->cursorPaginate($perPage, ['*'], 'cursor', is_string($cursor) ? $cursor : null);

            return response()->json([
                'data' => DossierResource::collection(collect($paginator->items()))->resolve(),
                'meta' => [
                    'per_page' => $paginator->perPage(),
                    'path' => $paginator->path(),
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'has_more' => $paginator->nextCursor() !== null,
                ],
            ]);
        }

        return DossierResource::collection(
            DossierListQuery::base($request)->paginate($perPage)
        );
    }

    public function store(StoreDossierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $year = (int) date('Y');
        $seq = Dossier::query()->whereYear('created_at', $year)->count() + 1;
        $reference = sprintf('D-%d-%03d', $year, $seq);

        $dossier = Dossier::query()->create([
            'client_id' => $data['client_id'],
            'reference' => $reference,
            'type' => $data['type'] ?? null,
            'statut' => $data['statut'] ?? 'En cours',
            'date_ouverture' => $data['date_ouverture'] ?? now()->toDateString(),
        ]);

        return (new DossierResource($dossier->load(['client.destination', 'documents'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Dossier $dossier): DossierResource
    {
        return new DossierResource(
            $dossier->load([
                'client.destination',
                'documents' => fn ($q) => $q->select(['id', 'dossier_id', 'original_filename', 'file_path', 'type_document', 'created_at']),
            ])
        );
    }

    public function update(UpdateDossierRequest $request, Dossier $dossier): DossierResource
    {
        $dossier->fill($request->validated());
        $dossier->save();

        return new DossierResource(
            $dossier->fresh()->load([
                'client.destination',
                'documents' => fn ($q) => $q->select(['id', 'dossier_id', 'original_filename', 'file_path', 'type_document', 'created_at']),
            ])
        );
    }

    public function destroy(Dossier $dossier): JsonResponse
    {
        $dossier->delete();

        return response()->json(['message' => 'Dossier supprimé.']);
    }
}
