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
        Schema::table('ad_divident_equity_change', function (Blueprint $table) {
            $table->smallInteger('process_statusid')->default(0)->comment('Процессын төлөв 0 - засах боломжтой, 1 - дууссан');
            $table->timestamp('completed_at')->nullable()->comment('Дуусгасан огноо');
            $table->unsignedBigInteger('completed_by')->nullable()->comment('Дуусгасан хэрэглэгч');

            $table->index(
                ['instid', 'startdate', 'enddate', 'prodcode', 'statusid', 'process_statusid'],
                'ad_divident_equity_change_process_idx'
            );
        });

        Schema::create('ad_divident_profit', function (Blueprint $table) {
            $table->id();
            $table->date('startdate');
            $table->date('enddate');
            $table->string('prodcode', 20);
            $table->decimal('dividendamount', 23, 8)->default(0);
            $table->smallInteger('summary')->default(0)->comment('0 - дэлгэрэнгүй, 1 - хураангуй');
            $table->smallInteger('zeroignore')->default(0)->comment('0 - бүгд, 1 - endbal 0 мөрийг хасна');
            $table->smallInteger('process_statusid')->default(0)->comment('Процессын төлөв 0 - засах боломжтой, 1 - дууссан');
            $table->timestamp('completed_at')->nullable()->comment('Дуусгасан огноо');
            $table->unsignedBigInteger('completed_by')->nullable()->comment('Дуусгасан хэрэглэгч');
            $table->smallInteger('statusid');
            $table->bigInteger('instid');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(
                ['instid', 'startdate', 'enddate', 'prodcode', 'statusid'],
                'ad_divident_profit_idx'
            );
            $table->index(
                ['instid', 'startdate', 'enddate', 'prodcode', 'statusid', 'process_statusid'],
                'ad_divident_profit_process_idx'
            );
        });

        Schema::create('ad_divident_profit_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profit_id');
            $table->integer('rowno');
            $table->integer('no')->nullable();
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
            $table->decimal('calc_balance', 23, 8)->nullable();
            $table->decimal('days', 18, 8)->nullable();
            $table->decimal('day_amount', 23, 8)->nullable();
            $table->decimal('day_weight', 18, 8)->nullable();
            $table->decimal('div_amount', 23, 8)->nullable();
            $table->decimal('div_percent', 18, 8)->nullable();
            $table->decimal('rate', 18, 8)->nullable();
            $table->decimal('dividend', 23, 8)->nullable();
            $table->decimal('taxamount', 23, 8)->nullable();
            $table->decimal('netamount', 23, 8)->nullable();
            $table->timestamps();

            $table->index(['profit_id', 'rowno'], 'ad_divident_profit_detail_idx');
            $table->foreign('profit_id')
                ->references('id')
                ->on('ad_divident_profit')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_divident_profit_detail');
        Schema::dropIfExists('ad_divident_profit');

        Schema::table('ad_divident_equity_change', function (Blueprint $table) {
            $table->dropIndex('ad_divident_equity_change_process_idx');
            $table->dropColumn(['process_statusid', 'completed_at', 'completed_by']);
        });
    }
};
