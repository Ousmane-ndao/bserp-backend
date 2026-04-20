<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentProgressRequest;
use App\Http\Requests\UpdateStudentProgressRequest;
use App\Models\Client;
use App\Models\StudentProgress;
use Illuminate\Http\JsonResponse;

class StudentProgressController extends Controller
{
    public function show(Client $client): JsonResponse
    {
        $progress = StudentProgress::query()->where('client_id', $client->id)->first();

        return response()->json(['data' => $this->formatPayload($client, $progress)]);
    }

    public function store(StoreStudentProgressRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (StudentProgress::query()->where('client_id', $data['client_id'])->exists()) {
            return response()->json(['message' => 'Un suivi existe déjà pour ce client.'], 422);
        }

        $client = Client::query()->findOrFail($data['client_id']);
        $progress = StudentProgress::query()->create([
            'client_id' => $client->id,
            'lettre_motivation' => $request->boolean('lettre_motivation'),
            'bulletins_enregistres' => $request->boolean('bulletins_enregistres'),
            'travail_effectue' => $request->boolean('travail_effectue'),
            'notes_saisies' => $request->boolean('notes_saisies'),
        ]);

        return response()->json(['data' => $this->formatPayload($client, $progress)], 201);
    }

    public function update(UpdateStudentProgressRequest $request, Client $client): JsonResponse
    {
        $progress = StudentProgress::query()->firstOrNew(['client_id' => $client->id]);
        $fields = ['lettre_motivation', 'bulletins_enregistres', 'travail_effectue', 'notes_saisies'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $progress->{$field} = $request->boolean($field);
            } elseif (! $progress->exists) {
                $progress->{$field} = false;
            }
        }

        $progress->client_id = $client->id;
        $progress->save();

        return response()->json(['data' => $this->formatPayload($client, $progress->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPayload(Client $client, ?StudentProgress $progress): array
    {
        return [
            'clientId' => (string) $client->id,
            'recordExists' => $progress !== null,
            'lettreMotivation' => $progress?->lettre_motivation ?? false,
            'bulletinsEnregistres' => $progress?->bulletins_enregistres ?? false,
            'travailEffectue' => $progress?->travail_effectue ?? false,
            'notesSaisies' => $progress?->notes_saisies ?? false,
        ];
    }
}
