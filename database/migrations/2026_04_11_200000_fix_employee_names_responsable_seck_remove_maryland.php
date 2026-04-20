<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Employee::query()->cursor() as $employee) {
            $name = (string) $employee->name;

            if (preg_match('/MARYLAND/iu', $name)) {
                $name = preg_replace('/\bMARYLAND\b/iu', '', $name) ?? '';
                $name = preg_replace('/\s+/u', ' ', trim($name));
            }

            if (in_array($employee->email, ['mme.diop.admin@bserp.com', 'mme.seck.admin@bserp.com'], true)) {
                $name = 'Mme Seck';
            }

            if ($name !== $employee->name) {
                $employee->update(['name' => $name]);
            }

            $syncName = $employee->fresh()->name;
            User::query()
                ->where('employee_id', $employee->id)
                ->update(['name' => $syncName]);
        }
    }

    public function down(): void
    {
        // Correction de données métier : pas de retour arrière automatique.
    }
};
