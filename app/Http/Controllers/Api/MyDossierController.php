<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Client;
use App\Models\Document;
use App\Support\DocumentCatalog;
use App\Support\RoleMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDossierController extends Controller
{
    /** Rôles staff autorisés à consulter le dossier d'un client via ?client_id= */
    private const STAFF_ROLES = [
        'directrice',
        'responsable_admin',
        'conseillere_pedagogique',
        'informaticien',
        'commercial',
        'accueil',
    ];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $client = $this->resolveClient($request);
        if (! $client) {
            return response()->json([
                'message' => 'Aucun dossier étudiant lié à ce compte. Connectez-vous avec l’email enregistré sur votre dossier.',
                'linked' => false,
            ], 404);
        }

        $client->load(['destination:id,name', 'dossiers:id,client_id,reference,type,statut']);

        $documentSelect = ['id', 'client_id', 'dossier_id', 'type_document', 'file_path', 'original_filename', 'size_bytes', 'mime', 'created_at'];
        if (Document::hasStatutColumn()) {
            $documentSelect[] = 'statut';
        }

        $documents = Document::query()
            ->select($documentSelect)
            ->where('client_id', $client->id)
            ->orderByDesc('id')
            ->get();

        $summary = DocumentCatalog::summarizeForClient($documents);
        $isOwner = strcasecmp((string) $user->email, (string) $client->email) === 0;

        return response()->json([
            'linked' => true,
            'isOwner' => $isOwner,
            'client' => [
                'id' => (string) $client->id,
                'prenom' => $client->prenom,
                'nom' => $client->nom,
                'email' => $client->email,
                'destination' => $client->destination?->name,
            ],
            'dossiers' => $client->dossiers->map(fn ($d) => [
                'id' => (string) $d->id,
                'reference' => $d->reference,
                'type' => $d->type,
                'statut' => $d->statut,
            ])->values()->all(),
            'progressPercent' => $summary['progressPercent'],
            'missingTypes' => $summary['missingTypes'],
            'categories' => $summary['categories'],
            'documents' => DocumentResource::collection($documents)->resolve(),
        ]);
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();
        $clientId = $request->query('client_id');

        if ($clientId && $this->userIsStaff($user)) {
            return Client::query()->find($clientId);
        }

        if (! $user?->email) {
            return null;
        }

        return Client::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->first();
    }

    private function userIsStaff(?\App\Models\User $user): bool
    {
        if (! $user?->employee?->role) {
            return false;
        }

        $roleKey = RoleMapper::toFrontendKey($user->employee->role->name);

        return in_array($roleKey, self::STAFF_ROLES, true);
    }
}
