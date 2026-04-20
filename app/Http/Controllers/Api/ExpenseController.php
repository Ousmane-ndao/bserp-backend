<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 20), 100);

        return ExpenseResource::collection(
            Expense::query()->orderByDesc('date_depense')->orderByDesc('id')->paginate($perPage)
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $expense = Expense::query()->create([
            'libelle' => $data['libelle'],
            'montant' => $data['amount'] ?? $data['montant'],
            'currency' => strtoupper(substr((string) ($data['currency'] ?? config('currency.code')), 0, 3)),
            'categorie' => $data['categorie'] ?? null,
            'date_depense' => $data['spent_at'] ?? $data['date_depense'] ?? now()->toDateString(),
        ]);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return new ExpenseResource($expense);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('libelle', $data)) {
            $expense->libelle = $data['libelle'];
        }
        if (array_key_exists('amount', $data) || array_key_exists('montant', $data)) {
            $expense->montant = $data['amount'] ?? $data['montant'] ?? $expense->montant;
        }
        if (array_key_exists('spent_at', $data) || array_key_exists('date_depense', $data)) {
            $expense->date_depense = $data['spent_at'] ?? $data['date_depense'] ?? $expense->date_depense;
        }
        if (array_key_exists('categorie', $data)) {
            $expense->categorie = $data['categorie'];
        }
        if (array_key_exists('currency', $data) && $data['currency'] !== null) {
            $expense->currency = strtoupper(substr((string) $data['currency'], 0, 3));
        }

        $expense->save();

        return (new ExpenseResource($expense->fresh()))->response();
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(null, 204);
    }
}
