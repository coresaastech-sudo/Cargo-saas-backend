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
        Schema::table('ad_divident_profit_detail', function (Blueprint $table) {
            $table->smallInteger('process_statusid')->default(0)->after('bank_acntno')->comment('Процессын төлөв 0 - хүлээгдэж байгаа, 1 - амжилттай');
            $table->timestamp('completed_at')->nullable()->after('process_statusid')->comment('Дуусгасан огноо');
            $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at')->comment('Дуусгасан хэрэглэгч');
            $table->bigInteger('jrno')->nullable()->after('completed_by')->comment('Гүйлгээний журналын дугаар');

            $table->index(['profit_id', 'process_statusid'], 'ad_divident_profit_detail_process_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_divident_profit_detail', function (Blueprint $table) {
            $table->dropIndex('ad_divident_profit_detail_process_idx');
            $table->dropColumn(['process_statusid', 'completed_at', 'completed_by', 'jrno']);
        });
    }
};
