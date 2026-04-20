<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_EMAIL = 'mme.diop.admin@bserp.com';

    private const NEW_EMAIL = 'mme.seck.admin@bserp.com';

    public function up(): void
    {
        DB::table('employees')
            ->where('email', self::OLD_EMAIL)
            ->update(['email' => self::NEW_EMAIL, 'updated_at' => now()]);

        DB::table('users')
            ->where('email', self::OLD_EMAIL)
            ->update(['email' => self::NEW_EMAIL, 'updated_at' => now()]);

        foreach (Employee::query()->cursor() as $employee) {
            $name = (string) $employee->name;
            if (preg_match('/MARYLAND/iu', $name)) {
                $name = preg_replace('/\bMARYLAND\b/iu', '', $name) ?? '';
                $name = preg_replace('/\s+/u', ' ', trim($name));
            }
            if ($name !== $employee->name) {
                $employee->update(['name' => $name]);
            }
            User::query()
                ->where('employee_id', $employee->id)
                ->update(['name' => $employee->fresh()->name]);
        }
    }

    public function down(): void
    {
        DB::table('employees')
            ->where('email', self::NEW_EMAIL)
            ->update(['email' => self::OLD_EMAIL, 'updated_at' => now()]);

        DB::table('users')
            ->where('email', self::NEW_EMAIL)
            ->update(['email' => self::OLD_EMAIL, 'updated_at' => now()]);
    }
};
