<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        DB::statement("DROP VIEW IF EXISTS VW_AD_CORPORATE_GATEWAY");

        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            $table->string('bankacntno', 50)->nullable()->comment('Банкны дансны дугаар')->change();
        });

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CORPORATE_GATEWAY AS
            SELECT
                CC.*,
                GP.NAME as BANKCODENAME,
                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
            FROM
                AD_CORPORATE_GATEWAY CC
                LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                LEFT JOIN VW_DICT_GP_CONST_063 GP ON GP.VALUE = CC.BANKCODE"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP VIEW IF EXISTS VW_AD_CORPORATE_GATEWAY");

        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            $table->string('bankacntno', 22)->nullable()->comment('Банкны дансны дугаар')->change();
        });

        DB::statement(
            "CREATE OR REPLACE VIEW VW_AD_CORPORATE_GATEWAY AS
            SELECT
                CC.*,
                GP.NAME as BANKCODENAME,
                GU1.ID || ' - ' || GU1.NAME AS CREATED_NAME,
                GU2.ID || ' - ' || GU2.NAME AS UPDATED_NAME
            FROM
                AD_CORPORATE_GATEWAY CC
                LEFT JOIN GP_INST_USER GU1 ON GU1.ID = CC.CREATED_BY
                LEFT JOIN GP_INST_USER GU2 ON GU2.ID = CC.UPDATED_BY
                LEFT JOIN VW_DICT_GP_CONST_063 GP ON GP.VALUE = CC.BANKCODE"
        );
    }
};
