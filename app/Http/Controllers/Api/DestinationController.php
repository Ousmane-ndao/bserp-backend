<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(): JsonResponse
    {
        $destinations = Destination::query()
            ->orderBy('region')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'type_compte', 'montant_total']);

        return response()->json($destinations->map(fn (Destination $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'region' => $d->region,
            'type_compte' => $d->type_compte,
            'montantTotal' => (string) $d->montant_total,
        ]));
    }

    public function update(Request $request, Destination $destination): JsonResponse
    {
        $validated = $request->validate([
            'montant_total' => ['sometimes', 'numeric', 'min:0', 'required_without:montantTotal'],
            'montantTotal' => ['sometimes', 'numeric', 'min:0', 'required_without:montant_total'],
        ]);

        $montant = $validated['montant_total'] ?? $validated['montantTotal'] ?? null;
        $destination->update(['montant_total' => $montant]);

        return response()->json([
            'id' => $destination->id,
            'name' => $destination->name,
            'region' => $destination->region,
            'type_compte' => $destination->type_compte,
            'montantTotal' => (string) $destination->montant_total,
        ]);
    }
}
