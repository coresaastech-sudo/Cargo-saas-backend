<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн кредит дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_cd', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');
            $table->string('name', 300)->nullable()->comment('Нэр');
            $table->string('name2', 300)->nullable()->comment('Нэр2');

            $table->string('acnt_type', 20)->nullable()->comment('Дансны төрөл');
            $table->date('actual_start_date')->nullable()->comment('Эхлэх огноо');
            $table->decimal('avail_balance', 23, 5)->nullable()->comment('Боломжит үлд');
            $table->decimal('block_amount_cash', 23, 5)->nullable()->comment('Битүүмж бэлэн');
            $table->decimal('block_amount_purch', 23, 5)->nullable()->comment('Битүүмж худалдан авалт');
            $table->string('brch_code', 20)->nullable()->comment('Салбар код');
            $table->string('brch_name', 200)->nullable()->comment('Салбар нэр');
            $table->string('brch_name2', 200)->nullable()->comment('Салбар нэр2');
            $table->decimal('cash_limit', 23, 5)->nullable()->comment('Бэлэн лимит');
            $table->string('class_name', 50)->nullable()->comment('Ангилал нэр');
            $table->string('class_name2', 50)->nullable()->comment('Ангилал нэр2');
            $table->string('class_no', 1)->nullable()->comment('Ангилал');
            $table->string('company_code', 10)->nullable()->comment('Байгууллагын код');
            $table->string('cur_code', 3)->nullable()->comment('Валют');
            $table->string('cust_code', 20)->nullable()->comment('Харилцагчийн дугаар');
            $table->unsignedBigInteger('cycle_no')->nullable()->comment('Цикл');
            $table->string('daily_basis_code', 10)->nullable()->comment('Хүү тооцох хоногын код');
            $table->string('description', 300)->nullable()->comment('Тайлбар');
            $table->date('due_date')->nullable()->comment('Дуусах огноо');
            $table->date('end_date')->nullable()->comment('Хаагдах огноо');
            $table->decimal('exp_cash_amount', 23, 5)->nullable()->comment('Хугц дуусах бэлэн дүн');
            $table->decimal('exp_interest_amount', 23, 5)->nullable()->comment('Хугц дуусах хүү дүн');
            $table->decimal('exp_purchase_amount', 23, 5)->nullable()->comment('Хугц дуусах худалдан авалт дүн');
            $table->decimal('exp_transfer_amount', 23, 5)->nullable()->comment('Хугц дуусах шилжүүлэг дүн');
            $table->string('get_with_secure', 1)->nullable();
            $table->smallInteger('grace_days')->nullable()->comment('Өршөөлийн хоног');
            $table->smallInteger('is_allow_partial_liq')->nullable()->comment('');
            $table->smallInteger('is_not_auto_class')->nullable()->comment('Автомат ангилах эсэх');
            $table->smallInteger('is_secure')->nullable()->comment('');
            $table->date('last_exp_date')->nullable()->comment('Сүүлийн дуусах огноо');
            $table->date('last_liquidate_date')->nullable()->comment('');
            $table->date('last_txn_date')->nullable()->comment('Сүүлийн гүйлгээний огноо');
            $table->decimal('min_pay_amt', 23, 5)->nullable()->comment('Минимум төлбөрийн дүн');
            $table->decimal('od_fee', 23, 5)->nullable();
            $table->decimal('ol_fee', 23, 5)->nullable();
            $table->decimal('other_fee', 23, 5)->nullable();
            $table->decimal('over_limit_amt', 23, 5)->nullable()->comment('Лимит хэтрэх дүн');
            $table->decimal('over_limit_percent', 23, 5)->nullable()->comment('Лимит хэтрэх хувь');
            $table->string('prod_code', 12)->nullable()->comment('Бүтээгдэхүүн код');
            $table->string('prod_code_name', 100)->nullable()->comment('Бүтээгдэхүүн нэр');
            $table->string('prod_code_name2', 100)->nullable()->comment('Бүтээгдэхүүн нэр2');
            $table->string('repayment_acnt', 20)->nullable()->comment('Эргэн төлөх данс');
            $table->string('repayment_mode', 1)->nullable()->comment('Эргэн төлөх арга');
            $table->string('repayment_mode_name', 50)->nullable()->comment('Эргэн төлөх нэр');
            $table->string('repayment_mode_name2', 50)->nullable()->comment('Эргэн төлөх нэр2');
            $table->string('repayment_type', 20)->nullable()->comment('Эргэн төлөх төрөл');
            $table->string('repayment_type_name', 50)->nullable()->comment('Эргэн төлөх төрөл нэр');
            $table->string('repayment_type_name2', 50)->nullable()->comment('Эргэн төлөх төрөл нэр2');
            $table->string('seg_code', 4)->nullable()->comment('Сегмент');
            $table->date('start_date')->nullable()->comment('Эхлэх огноо');
            $table->date('statement_date')->nullable()->comment('Хуулга гарах өдөр');
            $table->string('status_id', 4)->nullable();
            $table->string('status_id_name', 50)->nullable();
            $table->string('status_id_name2', 50)->nullable();
            $table->string('status_name', 20)->nullable();
            $table->string('status_name2', 50)->nullable();
            $table->string('status_sys', 1)->nullable();
            $table->smallInteger('sys_no')->nullable();
            $table->decimal('total_exp_amount', 23, 5)->nullable()->comment('Нийт цикл дүн');
            $table->decimal('total_limit', 23, 5)->nullable()->comment('Нийт цикл лимит');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['instid','userid','acnt_code']);
         });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_acnt_cd');
    }
};
