<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н хэрэглэгчийн гүйлгээний журнал бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_txn_journal', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн дугаар');
            $table->string('txn_acnt_code', 20)->comment('Гүйлгээний данс');
            $table->date('txn_date')->nullable()->comment('Гүйлгээний огноо');
            $table->decimal('txn_amount', 23, 5)->nullable()->comment('Гүйлгээний дүн');
            $table->string('cur_code', 3)->nullable()->comment('Валют');
            $table->string('txn_jrno', 30)->nullable()->comment('Журнал');
            $table->string('txn_corr_jrno', 30)->nullable()->comment('Буцаалтын гүйлгээний журнал');
            $table->string('txn_desc', 300)->nullable()->comment('Гүйлгээний утга');
            $table->string('txn_type', 1)->nullable()->comment('Гүйлгээний төрөл');
            $table->string('identity_type', 20)->nullable();

            $table->string('cont_acnt_code', 20)->nullable()->comment('Банк харьцсан данс');
            $table->decimal('cont_amount', 23, 5)->nullable()->comment('Банк харьцсан дүн');
            $table->string('cont_bank_code', 20)->nullable()->comment('Харьцсан банк код');
            $table->string('cont_cur_code', 3)->nullable()->comment('Банк харьцсан валют');
            $table->decimal('cont_rate', 23, 5)->nullable()->comment('Банк харьцсан ханш');
            $table->string('core_corr_jrno', 30)->nullable()->comment('Суурь систем журнал');
            $table->string('core_jrno', 30)->nullable()->comment('Суурь систем журнал');
            $table->string('err_desc', 500)->nullable()->comment('Алдааны тайлбар');
            $table->smallInteger('fee_id')->nullable()->comment('Шимтгэл дугаар');
            $table->decimal('fee_inst_amount', 23, 2)->nullable()->comment('Шимтгэл байгууллагын дүн');
            $table->decimal('fee_sys_amount', 23, 2)->nullable()->comment('Шимтгэл систем дүн');
            $table->string('internal_cont_acnt_code', 20)->nullable()->comment('Дотоод харьсан данс');
            $table->smallInteger('is_preview')->nullable()->comment('');
            $table->smallInteger('is_preview_fee')->nullable()->comment('');
            $table->smallInteger('is_supervisor')->nullable()->comment('');
            $table->smallInteger('is_tmw')->nullable()->comment('');
            $table->smallInteger('jr_item_no_and_incr')->nullable()->comment('Журнал бичилтын дугаар');
            $table->string('oper_code', 10)->nullable()->comment('Процесс код');
            $table->string('parent_jrno', 30)->nullable()->comment('Эцэг журнал');
            $table->decimal('rate', 23, 5)->nullable()->comment('');
            $table->string('source_type', 10)->nullable()->comment('Эх сурвалж');
            $table->string('tcust_addr', 300)->nullable()->comment('Гүйлгээнд бичигдсэн харилцагч хаяг');
            $table->string('tcust_contact', 20)->nullable()->comment('Гүйлгээнд бичигдсэн харилцагч холбоо барих');
            $table->string('tcust_name', 100)->nullable()->comment('Гүйлгээнд бичигдсэн харилцагч нэр');
            $table->string('tcust_register', 15)->nullable()->comment('Гүйлгээнд бичигдсэн харилцагчийн РД');
            $table->string('tcust_register_mask', 30)->nullable()->comment('Гүйлгээнд бичигдсэн харилцагчийн РД маск');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_txn_journal');
    }
};
