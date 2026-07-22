<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('gl_report_conf_column_cont_txn', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conf_column_id');
            $table->string('contacntno', 14);
            $table->string('conttrantype', 4);
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['conf_column_id', 'statusid'], 'gl_rep_conf_col_cont_txn_idx');
            $table->index(['conf_column_id', 'contacntno', 'conttrantype', 'statusid'], 'gl_rep_conf_col_cont_txn_lookup');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gl_report_conf_column_cont_txn');
    }
};
