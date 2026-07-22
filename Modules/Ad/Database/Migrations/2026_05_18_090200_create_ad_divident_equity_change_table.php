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
        Schema::create('ad_divident_equity_change', function (Blueprint $table) {
            $table->id();
            $table->date('startdate');
            $table->date('enddate');
            $table->string('prodcode', 20);
            $table->integer('rowno');
            $table->string('name', 200)->nullable();
            $table->string('id1', 50)->nullable();
            $table->string('custno', 20)->nullable();
            $table->date('txndate')->nullable();
            $table->string('acntno', 20)->nullable();
            $table->decimal('startbal', 23, 8)->nullable();
            $table->decimal('addamount', 23, 8)->nullable();
            $table->date('adddate')->nullable();
            $table->decimal('minusamount', 23, 8)->nullable();
            $table->date('minusdate')->nullable();
            $table->decimal('endbal', 23, 8)->nullable();
            $table->decimal('weight', 18, 8)->nullable();
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['instid', 'startdate', 'enddate', 'prodcode', 'statusid'], 'ad_divident_equity_change_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_divident_equity_change');
    }
};
