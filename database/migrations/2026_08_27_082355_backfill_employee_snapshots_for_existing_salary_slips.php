<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('salary_slips')
            ->join('employees', 'employees.id', '=', 'salary_slips.employee_id')
            ->whereNull('salary_slips.employee_snapshot')
            ->select([
                'salary_slips.id',
                'employees.first_name',
                'employees.last_name',
                'employees.email',
            ])
            ->orderBy('salary_slips.id')
            ->get()
            ->each(function (object $slip): void {
                DB::table('salary_slips')
                    ->where('id', $slip->id)
                    ->update([
                        'employee_snapshot' => json_encode([
                            'first_name' => $slip->first_name,
                            'last_name' => $slip->last_name,
                            'email' => $slip->email,
                            'ahv_number' => null,
                        ], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The snapshot column is removed by the schema migration.
    }
};
