<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cr_cust_ind', function (Blueprint $table) {
            if (!Schema::hasColumn('cr_cust_ind', 'partner_type')) {
                $table->string('partner_type', '2')->nullable()->comment('Хамтрагчийн төрөл, DIC105-аас утга авна');
            }
        });

        Schema::table('cr_cust_org', function (Blueprint $table) {
            if (!Schema::hasColumn('cr_cust_org', 'partner_type')) {
                $table->string('partner_type', '2')->nullable()->comment('Хамтрагчийн төрөл, DIC105-аас утга авна');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('cr_cust_ind', function (Blueprint $table) {
            if (Schema::hasColumn('cr_cust_ind', 'partner_type')) {
                $table->dropColumn('partner_type');
            }
        });

        Schema::table('cr_cust_org', function (Blueprint $table) {
            if (Schema::hasColumn('cr_cust_org', 'partner_type')) {
                $table->dropColumn('partner_type');
            }
        });
    }
};
