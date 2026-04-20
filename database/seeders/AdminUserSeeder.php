<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $employee = Employee::query()->where('email', 'admin@bserp.com')->first();

        if (! $employee) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@bserp.com'],
            [
                'name' => $employee->name,
                'password' => 'password',
                'employee_id' => $employee->id,
            ]
        );

        if (! $user->employee_id) {
            $user->employee_id = $employee->id;
            $user->save();
        }
    }
}
