<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gp_dic_mains', function (Blueprint $table) {
            $table->id();
            $table->string('dic_code', 20)->unique();
            $table->string('vw_name', 120);
            $table->string('description', 250);
            $table->integer('statusid')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gp_inst_consts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('gp_organizations')->nullOnDelete();
            $table->string('dic_code', 20);
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('name2', 160)->nullable();
            $table->string('value', 250)->nullable();
            $table->string('parent_code', 80)->nullable();
            $table->integer('listorder')->default(0);
            $table->integer('statusid')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'dic_code', 'code']);
        });

        DB::statement('
            CREATE OR REPLACE VIEW vw_gp_inst_consts AS
            SELECT
                id,
                listorder,
                organization_id,
                dic_code,
                code,
                name,
                name2,
                value,
                parent_code,
                statusid
            FROM gp_inst_consts
            WHERE statusid <> -1
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_gp_inst_consts');
        Schema::dropIfExists('gp_inst_consts');
        Schema::dropIfExists('gp_dic_mains');
    }
};
