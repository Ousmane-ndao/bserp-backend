<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::query()->with(['user', 'role']);

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where('name', 'like', $s)->orWhere('email', 'like', $s);
        }

        $perPage = min($request->integer('per_page', 20), 100);

        return EmployeeResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::query()->where('name', RoleMapper::toDbName($data['role']))->firstOrFail();

        $employee = Employee::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $role->id,
            'telephone' => $data['telephone'] ?? null,
            'statut' => $data['statut'] ?? 'Actif',
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'employee_id' => $employee->id,
        ]);

        return (new EmployeeResource($employee->load(['user', 'role'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $data = $request->validated();
        $user = $employee->user;

        if (isset($data['name'])) {
            $employee->name = $data['name'];
            if ($user) {
                $user->name = $data['name'];
            }
        }
        if (isset($data['email'])) {
            $employee->email = $data['email'];
            if ($user) {
                $user->email = $data['email'];
            }
        }
        if ($user && ! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($user) {
            $user->save();
        }

        if (isset($data['role'])) {
            $role = Role::query()->where('name', RoleMapper::toDbName($data['role']))->firstOrFail();
            $employee->role_id = $role->id;
        }

        if (array_key_exists('telephone', $data)) {
            $employee->telephone = $data['telephone'];
        }
        if (isset($data['statut'])) {
            $employee->statut = $data['statut'];
        }
        $employee->save();

        return new EmployeeResource($employee->fresh()->load(['user', 'role']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $user = $employee->user;
        $employee->delete();
        if ($user) {
            $user->delete();
        }

        return response()->json(['message' => 'Employé supprimé.']);
    }
}
