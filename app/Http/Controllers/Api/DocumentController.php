<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Dossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::query()
            ->select([
                'id', 'client_id', 'dossier_id', 'type_document',
                'file_path', 'original_filename', 'size_bytes', 'mime', 'created_at',
            ])
            ->with(['client:id,prenom,nom,email']);

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('dossier_id')) {
            $query->where('dossier_id', $request->integer('dossier_id'));
        }

        if ($request->filled('search')) {
            $s = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($s) {
                $q->where('type_document', 'like', $s)
                    ->orWhere('original_filename', 'like', $s)
                    ->orWhereHas('client', function ($cq) use ($s) {
                        $cq->where('prenom', 'like', $s)
                            ->orWhere('nom', 'like', $s)
                            ->orWhere('email', 'like', $s);
                    });
            });
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return DocumentResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        try {
            $dossier = Dossier::query()->with('client')->findOrFail($request->validated('dossier_id'));
            $file = $request->file('file');
            $storedPath = $file->store('documents', 'local');

            $document = Document::query()->create([
                'client_id' => $dossier->client_id,
                'dossier_id' => $dossier->id,
                'type_document' => $request->input('type_document', 'CNI ou Passeport'),
                'file_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
            ]);

            return (new DocumentResource($document->load('client')))
                ->response()
                ->setStatusCode(201);
        } catch (Throwable $e) {
            $requestId = (string) $request->header('X-Request-Id', $request->header('x-request-id', 'n/a'));

            Log::error('Document upload failed', [
                'request_id' => $requestId,
                'user_id' => optional($request->user())->id,
                'dossier_id' => $request->input('dossier_id'),
                'type_document' => $request->input('type_document'),
                'original_filename' => optional($request->file('file'))->getClientOriginalName(),
                'filesize' => optional($request->file('file'))->getSize(),
                'mime' => optional($request->file('file'))->getClientMimeType(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Echec de l'upload du document.",
                'request_id' => $requestId,
            ], 500);
        }
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('client'));
    }

    public function download(Document $document): StreamedResponse|Response
    {
        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename ?? basename($document->file_path)
        );
    }

    public function destroy(Document $document): JsonResponse
    {
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        $document->delete();

        return response()->json(['message' => 'Document supprimé.']);
    }
}
