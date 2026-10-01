<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommercialActivity;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialActivityController extends Controller
{
    private function currentRoleKey(?User $user): ?string
    {
        $user?->loadMissing('employee.role');

        if (! $user || ! $user->employee || ! $user->employee->role) {
            return null;
        }

        return RoleMapper::toFrontendKey($user->employee->role->name);
    }

    private function canViewAll(?User $user): bool
    {
        $roleKey = $this->currentRoleKey($user);

        return in_array($roleKey, ['directrice', 'responsable_admin', 'informaticien'], true);
    }

    private function canManageActivity(?User $user, CommercialActivity $activity): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        return (int) $activity->commercial_user_id === (int) ($user?->id ?? 0);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CommercialActivity::query()
            ->with(['commercialUser.employee.role', 'creator.employee.role', 'client'])
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if (! $this->canViewAll($user)) {
            $query->where('commercial_user_id', $user?->id ?? 0);
        }

        if ($request->filled('type')) {
            $query->where('type', 'like', '%'.$request->string('type')->toString().'%');
        }

        if ($request->filled('commercial_user_id')) {
            $query->where('commercial_user_id', $request->integer('commercial_user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->string('date_to')->toString());
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CommercialActivity::query();

        if (! $this->canViewAll($user)) {
            $query->where('commercial_user_id', $user?->id ?? 0);
        }

        if ($request->filled('commercial_user_id')) {
            $query->where('commercial_user_id', $request->integer('commercial_user_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', 'like', '%'.$request->string('type')->toString().'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->string('date_to')->toString());
        }

        $typeLabels = [
            'Appel',
            'Visite',
            'Ouverture de dossier',
            'Rendez-vous',
            'Prospect suivi',
            'Client suivi',
        ];

        $byType = [];
        foreach ($typeLabels as $label) {
            $byType[$label] = (clone $query)->where('type', $label)->count();
        }

        $byCommercial = (clone $query)
            ->select(
                'commercial_user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN type = 'Appel' THEN 1 ELSE 0 END) as appels"),
                DB::raw("SUM(CASE WHEN type = 'Visite' THEN 1 ELSE 0 END) as visites"),
                DB::raw("SUM(CASE WHEN type = 'Ouverture de dossier' THEN 1 ELSE 0 END) as ouvertures"),
                DB::raw("SUM(CASE WHEN type = 'Rendez-vous' THEN 1 ELSE 0 END) as rendez_vous"),
                DB::raw("SUM(CASE WHEN type = 'Prospect suivi' THEN 1 ELSE 0 END) as prospects_suivis"),
                DB::raw("SUM(CASE WHEN type = 'Client suivi' THEN 1 ELSE 0 END) as clients_suivis"),
            )
            ->groupBy('commercial_user_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $user = User::query()->with('employee.role')->find($row->commercial_user_id);

                return [
                    'id' => (int) $row->commercial_user_id,
                    'name' => $user?->employee?->name ?? $user?->name ?? 'Commercial',
                    'total' => (int) $row->total,
                    'appels' => (int) $row->appels,
                    'visites' => (int) $row->visites,
                    'ouvertures' => (int) $row->ouvertures,
                    'rendez_vous' => (int) $row->rendez_vous,
                    'prospects_suivis' => (int) $row->prospects_suivis,
                    'clients_suivis' => (int) $row->clients_suivis,
                ];
            })
            ->values()
            ->all();

        $stats = [
            'total_activities' => (clone $query)->count(),
            'total_commerciaux' => count($byCommercial),
            'by_type' => $byType,
            'by_commercial' => $byCommercial,
            'appels' => $byType['Appel'] ?? 0,
            'visites' => $byType['Visite'] ?? 0,
            'ouvertures' => $byType['Ouverture de dossier'] ?? 0,
            'rendez_vous' => $byType['Rendez-vous'] ?? 0,
            'prospects_suivis' => $byType['Prospect suivi'] ?? 0,
            'clients_suivis' => $byType['Client suivi'] ?? 0,
        ];

        return response()->json(['data' => $stats]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleKey = $this->currentRoleKey($user);

        if (! in_array($roleKey, ['directrice', 'responsable_admin', 'informaticien', 'commercial'], true)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'prospect_name' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string', 'max:255'],
            'commentary' => ['nullable', 'string'],
            'commercial_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $commercialUserId = $roleKey === 'commercial'
            ? $user->id
            : ($validated['commercial_user_id'] ?? $user->id);

        $activity = CommercialActivity::query()->create([
            'commercial_user_id' => $commercialUserId,
            'user_id' => $user->id,
            'client_id' => $validated['client_id'] ?? null,
            'client_name' => $validated['client_name'] ?? null,
            'prospect_name' => $validated['prospect_name'] ?? null,
            'type' => $validated['type'],
            'date' => $validated['date'],
            'time' => $validated['time'] ?? null,
            'objective' => $validated['objective'] ?? null,
            'result' => $validated['result'] ?? null,
            'commentary' => $validated['commentary'] ?? null,
        ]);

        return response()->json([
            'data' => $activity->fresh()->load(['commercialUser.employee.role', 'creator.employee.role', 'client']),
        ], 201);
    }

    public function show(CommercialActivity $commercialActivity): JsonResponse
    {
        $user = request()->user();

        if (! $this->canManageActivity($user, $commercialActivity)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        return response()->json([
            'data' => $commercialActivity->load(['commercialUser.employee.role', 'creator.employee.role', 'client']),
        ]);
    }

    public function update(Request $request, CommercialActivity $commercialActivity): JsonResponse
    {
        $user = $request->user();

        if (! $this->canManageActivity($user, $commercialActivity)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'prospect_name' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:255'],
            'result' => ['nullable', 'string', 'max:255'],
            'commentary' => ['nullable', 'string'],
            'commercial_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($this->currentRoleKey($user) === 'commercial' && isset($validated['commercial_user_id'])) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        if (! $this->canViewAll($user) && isset($validated['commercial_user_id'])) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        if (isset($validated['commercial_user_id']) && $this->canViewAll($user)) {
            $commercialActivity->commercial_user_id = $validated['commercial_user_id'];
        }

        foreach (['type','date','time','client_id','client_name','prospect_name','objective','result','commentary'] as $field) {
            if (array_key_exists($field, $validated)) {
                $commercialActivity->{$field} = $validated[$field];
            }
        }

        $commercialActivity->save();

        return response()->json([
            'data' => $commercialActivity->fresh()->load(['commercialUser.employee.role', 'creator.employee.role', 'client']),
        ]);
    }

    public function destroy(Request $request, CommercialActivity $commercialActivity): JsonResponse
    {
        $user = $request->user();

        if (! $this->canManageActivity($user, $commercialActivity)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $commercialActivity->delete();

        return response()->json(['message' => 'Activité commerciale supprimée.']);
    }
}
