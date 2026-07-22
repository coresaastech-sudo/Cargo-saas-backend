<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            UPDATE GP_const
            SET value_add1 = CASE code
                WHEN 'employees_count_02' THEN '1'
                WHEN 'employees_count_03' THEN '11'
                WHEN 'employees_count_04' THEN '51'
                WHEN 'employees_count_05' THEN '201'
                WHEN 'employees_count_06' THEN '501'
                WHEN 'employees_count_07' THEN '1001'
            END,
            value_add2 = CASE code
                WHEN 'employees_count_02' THEN '10'
                WHEN 'employees_count_03' THEN '50'
                WHEN 'employees_count_04' THEN '200'
                WHEN 'employees_count_05' THEN '500'
                WHEN 'employees_count_06' THEN '1000'
                WHEN 'employees_count_07' THEN NULL
            END
            WHERE code IN (
                'employees_count_02',
                'employees_count_03',
                'employees_count_04',
                'employees_count_05',
                'employees_count_06',
                'employees_count_07'
            )
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
};
