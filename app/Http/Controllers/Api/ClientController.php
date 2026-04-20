<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Destination;
use App\Services\ClientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientAccountService $accountService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()
            ->select([
                'id', 'prenom', 'nom', 'email', 'telephone',
                'date_naissance', 'etablissement', 'niveau_etude',
                'destination_id', 'date_ouverture', 'created_at',
            ])
            ->with(['destination:id,name,region,type_compte']);

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($s) {
                $q->where('nom', 'like', $s)
                    ->orWhere('prenom', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('telephone', 'like', $s);
            });
        }

        if ($request->filled('destination_id')) {
            $query->where('destination_id', $request->integer('destination_id'));
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return ClientResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function options(Request $request): JsonResponse
    {
        $query = Client::query()
            ->select(['id', 'prenom', 'nom'])
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($s) {
                $q->where('nom', 'like', $s)
                    ->orWhere('prenom', 'like', $s);
            });
        }

        $limit = min(max($request->integer('limit', 100), 1), 200);
        $rows = $query->limit($limit)->get();

        $data = $rows->map(fn ($r) => [
            'id' => (string) $r->id,
            'nom' => $r->nom,
            'prenom' => $r->prenom,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $destination = Destination::query()->findOrFail($data['destination_id']);

        $client = Client::query()->create([
            'prenom' => $data['prenom'],
            'nom' => $data['nom'],
            'date_naissance' => $data['date_naissance'] ?? null,
            'etablissement' => $data['etablissement'] ?? null,
            'niveau_etude' => $data['niveau_etude'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'],
            'destination_id' => $data['destination_id'],
            'date_ouverture' => $data['date_ouverture'] ?? null,
        ]);

        $this->accountService->syncForClient($client, $destination, $data);

        return (new ClientResource($client->load('destination')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load('destination'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $data = $request->validated();

        if ($data !== []) {
            $client->fill(array_intersect_key($data, array_flip([
                'prenom', 'nom', 'date_naissance', 'etablissement', 'niveau_etude',
                'telephone', 'email', 'destination_id', 'date_ouverture',
            ])));
            $client->save();
        }

        $destination = Destination::query()->findOrFail($client->destination_id);
        $this->accountService->syncForClient($client->fresh(), $destination, $request->all());

        return new ClientResource($client->fresh()->load('destination'));
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(['message' => 'Client supprimé.']);
    }
}
