<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cr_cust_address', function (Blueprint $table) {
            // 9,6 -> 180.123456 хүртэл хадгална (≈ 0.11 м нарийвчлал)
            $table->decimal('coord_lon', 9, 6)->nullable()->comment('Координат уртраг (longitude)');
            // Lat-д 8,6 байхад хангалттай ч ижил байлгавал амар
            $table->decimal('coord_lat', 9, 6)->nullable()->comment('Координат өргөрөг (latitude)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cr_cust_address', function (Blueprint $table) {
            $table->dropColumn(['coord_lon', 'coord_lat']);
        });
    }
};
