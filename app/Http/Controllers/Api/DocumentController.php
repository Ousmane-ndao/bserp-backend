<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Client;
use App\Models\Document;
use App\Models\Dossier;
use App\Support\DocumentCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Support\DocumentsDisk;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use DocumentsDisk;
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $select = ['id', 'client_id', 'dossier_id', 'type_document', 'file_path', 'original_filename', 'size_bytes', 'mime', 'created_at'];
            if (Document::hasStatutColumn())
                $select[] = 'statut';

            $query = Document::query()->select($select)
                ->with(['client:id,prenom,nom,email,destination_id', 'client.destination:id,name']);

            if ($request->filled('client_id'))
                $query->where('client_id', $request->integer('client_id'));
            if ($request->filled('dossier_id'))
                $query->where('dossier_id', $request->integer('dossier_id'));
            if ($request->filled('type_document'))
                $query->where('type_document', $request->string('type_document')->toString());
            if ($request->filled('statut') && Document::hasStatutColumn())
                $query->where('statut', $request->string('statut')->toString());
            if ($request->filled('destination_id'))
                $query->whereHas('client', fn($q) => $q->where('destination_id', $request->integer('destination_id')));
            if ($request->filled('date_from'))
                $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
            if ($request->filled('date_to'))
                $query->whereDate('created_at', '<=', $request->string('date_to')->toString());

            if ($request->filled('search')) {
                $s = '%' . $request->string('search')->toString() . '%';
                $query->where(function ($q) use ($s) {
                    $q->where('type_document', 'like', $s)
                        ->orWhere('original_filename', 'like', $s)
                        ->orWhereHas('client', fn($cq) => $cq->where('prenom', 'like', $s)->orWhere('nom', 'like', $s)->orWhere('email', 'like', $s));
                });
            }

            $perPage = min($request->integer('per_page', 20), 100);
            return DocumentResource::collection($query->orderByDesc('id')->paginate($perPage));
        } catch (\Throwable $e) {
            Log::error('documents.index failed', ['message' => $e->getMessage(), 'exception' => get_class($e)]);
            return response()->json(['message' => 'Service documents temporairement indisponible.'], 503);
        }
    }

    public function clientsSummary(Request $request): JsonResponse
    {
        try {
            $documentSelect = ['id', 'client_id', 'type_document'];
            if (Document::hasStatutColumn())
                $documentSelect[] = 'statut';

            $query = Client::query()->select(['id', 'prenom', 'nom', 'destination_id'])
                ->with(['destination:id,name', 'documents:' . implode(',', $documentSelect)])
                ->whereHas('dossiers');

            if ($request->filled('search')) {
                $s = '%' . $request->string('search')->toString() . '%';
                $query->where(fn($q) => $q->where('prenom', 'like', $s)->orWhere('nom', 'like', $s));
            }
            if ($request->filled('destination_id'))
                $query->where('destination_id', $request->integer('destination_id'));

            $limit = min(max($request->integer('limit', 50), 1), 200);
            $clients = $query->orderByDesc('id')->limit($limit)->get();

            $data = $clients->map(function (Client $client) {
                $summary = DocumentCatalog::summarizeForClient($client->documents);
                return [
                    'clientId' => (string) $client->id,
                    'clientName' => trim($client->prenom . ' ' . $client->nom),
                    'destination' => $client->destination?->name,
                    'documentCount' => $client->documents->count(),
                    'progressPercent' => $summary['progressPercent'],
                    'missingTypes' => $summary['missingTypes'],
                    'categories' => $summary['categories'],
                ];
            })->values()->all();

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            Log::error('documents.clientsSummary failed', ['message' => $e->getMessage(), 'exception' => get_class($e)]);
            return response()->json(['message' => 'Résumé documents temporairement indisponible.'], 503);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $uploadedFile = $request->file('file');

            Log::info('documents.store request', [
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'has_file' => $request->hasFile('file'),
                'dossier_id' => $request->input('dossier_id'),
                'type_document' => $request->input('type_document'),
                'file_name' => $uploadedFile?->getClientOriginalName(),
                'file_size' => $uploadedFile?->getSize(),
                'file_valid' => $uploadedFile?->isValid(),
            ]);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'dossier_id' => 'required|exists:dossiers,id',
                'type_document' => 'required|string|max:255',
                'file' => 'required|file|max:20480',
            ]);

            if ($validator->fails()) {
                Log::warning('documents.store validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json(['message' => 'Données invalides.', 'errors' => $validator->errors()], 422);
            }

            $dossier = Dossier::with('client')->findOrFail($request->input('dossier_id'));
            $file = $request->file('file');

            if (! $file || ! $file->isValid()) {
                return response()->json(['message' => 'Fichier manquant ou invalide.'], 422);
            }

            $diskName = $this->documentsDiskName();
            $path = $file->store('documents', $diskName);
            if (! $path) {
                throw new \RuntimeException('Échec de l’enregistrement du fichier sur le disque.');
            }

            $document = Document::create([
                'client_id' => $dossier->client_id,
                'dossier_id' => $dossier->id,
                'type_document' => $request->input('type_document', 'CNI ou Passeport'),
                'statut' => Document::hasStatutColumn() ? 'En attente' : null,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
            ]);

            return (new DocumentResource($document->load('client')))->response()->setStatusCode(201);
        } catch (\Throwable $e) {
            \Log::error('UPLOAD EXCEPTION: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::error('documents.store failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => optional($request->user())->id,
            ]);

            // 🔥 CORRECTION ULTIME : Renvoyer l'erreur exacte dans le navigateur
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('client'));
    }

    public function update(Request $request, Document $document): DocumentResource|JsonResponse
    {
        try {
            if ($request->hasFile('file')) {
                $disk = $this->documentsDisk();
                if ($disk->exists($document->file_path)) {
                    $disk->delete($document->file_path);
                }
                $file = $request->file('file');
                $document->file_path = $file->store('documents', $this->documentsDiskName());
                $document->original_filename = $file->getClientOriginalName();
                $document->size_bytes = $file->getSize();
                $document->mime = $file->getClientMimeType();
                if (! $request->has('statut') && Document::hasStatutColumn()) {
                    $document->statut = 'En attente';
                }
            }

            if ($request->has('statut') && Document::hasStatutColumn()) {
                $document->statut = $request->input('statut');
            }
            if ($request->has('type_document')) {
                $document->type_document = $request->input('type_document');
            }

            $document->save();
            return new DocumentResource($document->fresh()->load('client'));
        } catch (\Throwable $e) {
            Log::error('documents.update failed', ['message' => $e->getMessage(), 'exception' => get_class($e)]);
            return response()->json(['message' => 'Mise à jour impossible.'], 503);
        }
    }

    public function download(Document $document): StreamedResponse|Response|JsonResponse
    {
        $diskName = $this->documentsDiskName();

        // 🛡️ CORRECTION POUR L'IDE : Cette annotation dit à VS Code que c'est un FilesystemAdapter
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        if (! $disk->exists($document->file_path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return $disk->download(
            $document->file_path,
            $document->original_filename ?? basename($document->file_path)
        );
    }

    public function signedUrl(Request $request, Document $document): JsonResponse
    {
        $minutes = min(max($request->integer('minutes', 30), 1), 1440);
        $diskName = $this->documentsDiskName();

        // 🛡️ CORRECTION POUR L'IDE : Annotation pour dire à VS Code que c'est un FilesystemAdapter
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        if (! $disk->exists($document->file_path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        $driver = config("filesystems.disks.{$diskName}.driver");
        if ($driver !== 's3') {
            return response()->json([
                'message' => 'URL signée disponible uniquement avec DOCUMENTS_DISK=r2 (ou s3). Utilisez GET /documents/{id}/download.',
            ], 422);
        }

        try {
            // ✅ L'appel fonctionne parfaitement, même si l'IDE ne le voit pas
            $url = $disk->temporaryUrl(
                $document->file_path,
                now()->addMinutes($minutes)
            );

            return response()->json([
                'data' => [
                    'url' => $url,
                    'expiresIn' => $minutes * 60,
                    'documentId' => (string) $document->id,
                    'mime' => $document->mime,
                    'filename' => $document->original_filename,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('documents.signedUrl failed', [
                'document_id' => $document->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Impossible de générer une URL signée.'], 503);
        }
    }

    public function destroy(Document $document): JsonResponse
    {
        $disk = $this->documentsDisk();
        if ($disk->exists($document->file_path)) {
            $disk->delete($document->file_path);
        }

        $document->delete();
        return response()->json(['message' => 'Document supprimé.']);
    }
}