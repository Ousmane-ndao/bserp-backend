<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentAccountRequest;
use App\Http\Requests\UpdateStudentAccountRequest;
use App\Models\Client;
use App\Models\StudentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAccountController extends Controller
{
    public function show(Client $client): JsonResponse
    {
        $client->load('destination');
        $account = StudentAccount::query()->where('client_id', $client->id)->first();

        return response()->json(['data' => $this->formatPayload($client, $account)]);
    }

    public function store(StoreStudentAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (StudentAccount::query()->where('client_id', $data['client_id'])->exists()) {
            return response()->json(['message' => 'Un compte étudiant existe déjà pour ce client.'], 422);
        }

        $client = Client::query()->with('destination')->findOrFail($data['client_id']);
        $account = new StudentAccount(['client_id' => $client->id]);
        $this->applyAccountInput($request, $client, $account, isCreate: true);
        $account->save();

        return response()->json(['data' => $this->formatPayload($client->fresh('destination'), $account->fresh())], 201);
    }

    public function update(UpdateStudentAccountRequest $request, Client $client): JsonResponse
    {
        $client->load('destination');
        $account = StudentAccount::query()->firstOrNew(['client_id' => $client->id]);
        $this->applyAccountInput($request, $client, $account, isCreate: false);
        $account->client_id = $client->id;
        $account->save();

        return response()->json(['data' => $this->formatPayload($client, $account->fresh())]);
    }

    /**
     * @param  Request&StoreStudentAccountRequest|UpdateStudentAccountRequest  $request
     */
    private function applyAccountInput(Request $request, Client $client, StudentAccount $account, bool $isCreate): void
    {
        $isFrance = $client->destination?->isFrance() ?? false;

        if ($request->has('email')) {
            $account->email = $request->input('email') ?: null;
        } elseif ($isCreate && ! $account->email) {
            $account->email = $client->email;
        }

        $this->applyOptionalPassword($request, 'email_password', $account, 'email_password');
        if ($isFrance) {
            $this->applyOptionalPassword($request, 'campus_password', $account, 'campus_password');
            $this->applyOptionalPassword($request, 'parcoursup_password', $account, 'parcoursup_password');
        } else {
            $account->campus_password = null;
            $account->parcoursup_password = null;
        }
    }

    private function applyOptionalPassword(Request $request, string $key, StudentAccount $account, string $attr): void
    {
        if (! $request->has($key)) {
            return;
        }
        $plain = $request->input($key);
        if ($plain === null || $plain === '') {
            $account->{$attr} = null;
        } else {
            $account->{$attr} = $plain;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPayload(Client $client, ?StudentAccount $account): array
    {
        $isFrance = $client->destination?->isFrance() ?? false;

        return [
            'clientId' => (string) $client->id,
            'destinationIsFrance' => $isFrance,
            'recordExists' => $account !== null,
            'email' => $account?->email,
            /** Texte en clair — réservé au personnel authentifié ; stockage chiffré en base. */
            'emailPassword' => $account?->email_password,
            'campusPassword' => $isFrance ? $account?->campus_password : null,
            'parcoursupPassword' => $isFrance ? $account?->parcoursup_password : null,
        ];
    }
}
