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
        DB::statement("INSERT INTO GP_const(code, value, name, name2, value_add1, value_add2, listorder, statusid, is_show_front, created_by, created_at, updated_by, updated_at, parent_code, instid
        ) VALUES 
            ('employees_count', 'employees_count', 'Хуулийн этгээдийн нийт ажиллагсдын тоо', 'Total number of employees of the legal entity', null, null, 101, 1, 1, 113, null, null, null, null, 1),
            ('employees_count_01', '01', 'Бусад', 'Others', null, null, 1, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_02', '02', '10 хүртэл ажилтантай', 'Up to 10 employees', null, null, 2, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_03', '03', '10-50 хүртэл ажилтантай', 'With 10-50 employees', null, null, 3, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_04', '04', '50-200 хүртэл ажилтантай', 'With 50-200 employees', null, null, 4, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_05', '05', '200-500 хүртэл ажилтантай', 'With 200-500 employees', null, null, 5, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_06', '06', '500-1000 хүртэл ажилтантай', 'With 500-1000 employees', null, null, 6, 1, 1, 113, null, null, null, 'employees_count', 1),
            ('employees_count_07', '07', '1000 дээш ажилтантай', 'More than 1000 employees', null, null, 7, 1, 1, 113, null, null, null, 'employees_count', 1)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
};
