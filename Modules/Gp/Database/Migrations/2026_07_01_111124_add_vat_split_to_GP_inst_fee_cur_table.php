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
        Schema::table('GP_inst_fee_cur', function (Blueprint $table) {
            if (!Schema::hasColumn('GP_inst_fee_cur', 'vat_split_percent')) {
                $table->decimal('vat_split_percent', 23, 8)->nullable()->comment('НӨАТ хуваах хувь');
            }
            if (!Schema::hasColumn('GP_inst_fee_cur', 'vat_txncode')) {
                $table->string('vat_txncode', 20)->nullable()->comment('НӨАТ өглөгийн гүйлгээний код');
            }
        });

        $this->refreshView();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_FEE_CUR_LIST");

        Schema::table('GP_inst_fee_cur', function (Blueprint $table) {
            if (Schema::hasColumn('GP_inst_fee_cur', 'vat_txncode')) {
                $table->dropColumn('vat_txncode');
            }
            if (Schema::hasColumn('GP_inst_fee_cur', 'vat_split_percent')) {
                $table->dropColumn('vat_split_percent');
            }
        });

        $this->refreshView();
    }

    private function refreshView()
    {
        DB::statement("DROP VIEW IF EXISTS VW_GP_INST_FEE_CUR_LIST");
        DB::statement("CREATE OR REPLACE VIEW VW_GP_INST_FEE_CUR_LIST AS
                        SELECT FC.*,
                                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
                        FROM GP_INST_FEE_CUR FC
                        LEFT JOIN GP_INST_USER GU1 ON GU1.ID = FC.CREATED_BY
                        LEFT JOIN GP_INST_USER GU2 ON GU2.ID = FC.UPDATED_BY");
    }
};
