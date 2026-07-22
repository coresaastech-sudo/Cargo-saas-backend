<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн зээлийн дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_ln', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');
            $table->string('name', 300)->nullable()->comment('Нэр');
            $table->string('name2', 300)->nullable()->comment('Нэр2');

            $table->unsignedBigInteger('acnt_manager')->nullable()->comment('Хариуцагч');
            $table->string('acnt_manager_name', 100)->nullable()->comment('Хариуцагч нэр');
            $table->string('acnt_type', 20)->nullable()->comment('Дансны төрөл');
            $table->date('acquired_date')->nullable()->comment('');
            $table->smallInteger('acquired_seq_txn')->nullable()->comment('');
            $table->decimal('acr_baseint_bal', 23, 5)->nullable()->comment('Хуримтлагдсан үнд хүү үлд');
            $table->decimal('acr_commint_bal', 23, 5)->nullable()->comment('Хуримтлагдсан ком хүү үлд');
            $table->smallInteger('active_nrs_version')->nullable()->default(0)->comment('Идвэхтэй хуваарь');
            $table->decimal('adv_amount', 23, 5)->nullable()->default(0)->comment('Олгосон дүн');
            $table->date('adv_date')->nullable()->comment('Олгосон огноо');
            $table->smallInteger('allowed_cam')->nullable()->comment('');
            $table->decimal('approv_amount', 23, 5)->nullable()->default(0)->comment('Зөвшөөрсөн дүн');
            $table->date('approv_date')->nullable()->comment('Зөвшөөрсөн огноо');
            $table->smallInteger('autochg_cls')->nullable()->comment('Авто ангилал шилжих эсэх');
            $table->string('autochg_due_cls', 10)->nullable()->comment('Авто ангилал дуусах');
            $table->smallInteger('autochg_formulaid_cls')->nullable()->comment('Авто ангилал шилжих товъёо');
            $table->string('autochg_option_cls', 10)->nullable()->comment('Авто ангилал шилжих сонголт');
            $table->smallInteger('autooff_cls_int')->nullable()->comment('');
            $table->smallInteger('autooff_duedays_int')->nullable()->comment('');
            $table->string('autooff_dueopt_int', 10)->nullable()->comment('');
            $table->smallInteger('autooff_int')->nullable()->comment('');
            $table->string('autooff_option_int', 10)->nullable()->comment('');
            $table->decimal('avail_com_bal', 23, 5)->nullable()->comment('Боломжит ком үлд');
            $table->decimal('bill_baseint_bal', 23, 5)->nullable()->comment('');
            $table->date('bill_baseint_date')->nullable()->comment('');
            $table->decimal('bill_comint_bal', 23, 5)->nullable()->comment('');
            $table->decimal('bill_commint_bal_on', 23, 5)->nullable()->comment('');
            $table->date('bill_fine_date')->nullable()->comment('');
            $table->decimal('bill_fineb_bal', 23, 5)->nullable()->comment('');
            $table->decimal('bill_finep_bal', 23, 5)->nullable()->comment('');
            $table->decimal('bill_princ_bal', 23, 5)->nullable()->comment('');
            $table->date('bill_princ_date')->nullable()->comment('');
            $table->string('brch_code', 6)->nullable()->comment('Салбар');
            $table->string('brch_name', 200)->nullable()->comment('Салбар нэр');
            $table->string('cat_code', 20)->nullable()->comment('Категори');
            $table->string('cat_name', 200)->nullable()->comment('Категори нэр');
            $table->string('cat_sub_code', 20)->nullable()->comment('Дэд категори');
            $table->string('cat_sub_name', 200)->nullable()->comment('Дэд категори нэр');
            $table->string('class_name', 50)->nullable()->comment('Ангилал нэр');
            $table->smallInteger('class_no')->nullable()->comment('Ангилал');
            $table->smallInteger('class_no_qlt')->nullable()->comment('Ангилал чанар');
            $table->smallInteger('class_no_trm')->nullable()->comment('Ангилал хугацаа');
            $table->string('class_qlt_name', 200)->nullable()->comment('Ангилал чанар нэр');
            $table->string('class_trm_name', 50)->nullable()->comment('Ангилал хугацаа нэр');
            $table->date('closed_date')->nullable()->comment('Хаагдсан огноо');
            $table->smallInteger('com_revolving')->nullable()->default(0)->comment('');
            $table->string('company_code', 4)->nullable()->comment('Байгууллагын код');
            $table->decimal('cont_available', 23, 5)->nullable()->comment('');
            $table->smallInteger('crt_billbint_topay')->nullable()->comment('');
            $table->string('cur_code', 3)->nullable()->comment('Валют')->comment('');
            $table->string('cust_code', 20)->nullable()->comment('Харилцагчийн дугаар');
            $table->string('cust_name', 300)->nullable()->comment('Харилцагчийн нэр');
            $table->string('cust_name2', 300)->nullable()->comment('Харилцагчийн нэр2');
            $table->smallInteger('cust_type')->nullable()->comment('Харилцагчийн төрөл');
            $table->string('daily_basis_code', 20)->nullable()->comment('Хүү тооцох хоногын код');
            $table->smallInteger('def_term_unit')->nullable()->comment('');
            $table->date('end_date')->nullable()->comment('Дуусах огноо');
            $table->smallInteger('extend_count')->nullable()->default(0)->comment('Сунгалт хийсэн тоо');
            $table->string('fine_condition', 10)->nullable()->comment('Нэмэгдүүлсэн хүү тооцох нөхцөл');
            $table->smallInteger('fine_grace')->nullable()->comment('Нэмэгдүүлсэн хүү тооцох хүлээх хоног');
            $table->decimal('fine_min_duebal', 23, 5)->nullable()->comment('Нэмэгдүүлсэн хамгийн бага хүү үлд');
            $table->unsignedBigInteger('first_acnt_manager')->nullable()->comment('Анхны хариуцагч');
            $table->string('first_acnt_manager_name', 100)->nullable()->comment('Анхны хариуцагч нэр');
            $table->smallInteger('flag_move_sa')->nullable()->default(0)->comment('');
            $table->string('flag_move_sa_name', 50)->nullable()->comment('');
            $table->smallInteger('flag_sec')->nullable()->default(0)->comment('');
            $table->smallInteger('flag_stopped')->nullable()->default(0)->comment('');
            $table->smallInteger('flag_stopped_int')->nullable()->default(0)->comment('');
            $table->string('flag_stopped_int_name', 50)->nullable()->comment('');
            $table->string('flag_stopped_name', 50)->nullable()->comment('');
            $table->smallInteger('flag_wroff_int')->nullable()->default(0)->comment('');
            $table->smallInteger('flag_wroff_princ')->nullable()->default(0)->comment('');
            $table->smallInteger('is_browse_acnt_other_com')->nullable()->comment('');
            $table->smallInteger('is_linked_secz')->nullable()->default(0)->comment('');
            $table->smallInteger('is_not_auto_class')->nullable()->default(0)->comment('');
            $table->smallInteger('is_secure')->nullable()->comment('');
            $table->date('last_accrual_date')->nullable()->comment('Сүүлийн хүү хуримтлуулсан огноо');
            $table->smallInteger('last_bill_no')->nullable()->default(0)->comment('Сүүлийн төлбөрийн дугаар');
            $table->smallInteger('last_seq_txn')->nullable()->comment('Сүүлийн төлбөрийн гүйлгээний дугаар');
            $table->date('last_txn_date')->nullable()->comment('Сүүлийн гүйлгээний огноо');
            $table->decimal('limit', 23, 5)->nullable()->comment('Лимит');
            $table->string('los_acnt_code', 20)->nullable()->comment('');
            $table->smallInteger('los_multi_acnt')->nullable()->default(0)->comment('');
            $table->smallInteger('max_term_unit')->nullable()->comment('Хамгийн их хугацаа');
            $table->smallInteger('min_term_unit')->nullable()->comment('Хамгийн бага хугацаа');
            $table->decimal('next_schd_amt', 23, 5)->nullable()->comment('Дараагын хуваарь дүн');
            $table->date('next_schd_date')->nullable()->comment('Дараагын хуваарийн өдөр');
            $table->decimal('next_schd_int', 23, 5)->nullable()->comment('Дараагын хуваарийн хүүний дүн');
            $table->smallInteger('payment_method')->nullable()->default(0)->comment('Төлбөрийн хэлбэр');
            $table->decimal('prepaid_baseint_bal', 23, 5)->nullable()->comment('Урьдчилан төлсөн үндсэн хүүний дүн');
            $table->decimal('princ_bal', 23, 5)->nullable()->comment('Үлдэгдэл');
            $table->decimal('princ_bal_on', 23, 5)->nullable()->comment('Онолын үлдэгдэл');
            $table->string('prod_code', 12)->nullable()->comment('Бүтээгдэхүүн');
            $table->string('prod_name', 200)->nullable()->comment('Бүтээгдэхүүн нэр');
            $table->string('prod_type', 20)->nullable()->comment('Бүтээгдэхүүний төрөл');
            $table->string('purpose', 12)->nullable()->comment('Зээлийн зориулалт');
            $table->string('purpose_name', 200)->nullable()->comment('Зээлийн зориулалт нэр');
            $table->string('repay_acnt_code', 20)->nullable()->comment('Эргэн төлөлтийн данс');
            $table->string('repay_acnt_code_other_com', 20)->nullable();
            $table->string('repay_acnt_name', 300)->nullable()->comment('Эргэн төлөлтийн данс нэр');
            $table->smallInteger('repay_acnt_sys_no')->nullable()->comment('Эргэн төлөлтийн дансны систем');
            $table->smallInteger('repay_priority')->nullable()->default(0)->comment('Эргэн төлөлт дараалал');
            $table->decimal('revol_amt', 23, 5)->nullable()->comment('');
            $table->date('sa_date')->nullable()->comment('');
            $table->string('sec_acnt_code', 20)->nullable()->comment('Барьцаа хөрөнгийн данс');
            $table->string('sec_acnt_name', 300)->nullable()->comment('Барьцаа хөрөнгийн дансны нэр');
            $table->string('sec_from_acnt', 20)->nullable()->comment('');
            $table->string('sec_inc_exp_acnt', 20)->nullable()->comment('');
            $table->string('sec_tmp_acnt', 20)->nullable()->comment('');
            $table->string('sec_to_acnt', 20)->nullable()->comment('');
            $table->smallInteger('sec_type')->nullable()->default(0)->comment('Барьцаа хөрөнгийн төрөл');
            $table->string('seg_code', 4)->nullable()->comment('Сегмент');
            $table->string('seg_name', 200)->nullable()->comment('Сегмент нэр');
            $table->smallInteger('slevel')->nullable()->default(1)->comment('Түвшин');
            $table->date('sold_date')->nullable()->comment('Зарагдсан огноо');
            $table->smallInteger('sold_seq_txn')->nullable()->comment('Зарагдсан гүйлгээний дугаар');
            $table->date('start_date')->nullable()->comment('Эхлэх огноо');
            $table->string('status', 1)->nullable()->default('N')->comment('Төлөв');
            $table->string('status_name', 20)->nullable()->comment('Төлөв нэр');
            $table->string('sub_purpose', 12)->nullable()->comment('Дэд зориулалт');
            $table->string('sub_purpose_name', 200)->nullable()->comment('Дэд зориулалт нэр');
            $table->smallInteger('sys_no')->nullable()->comment('Систем');
            $table->string('term_basis', 1)->nullable()->comment('Хүү тооцох хугацааны төрөл');
            $table->smallInteger('term_len')->nullable()->default(0)->comment('Хугацаа');
            $table->decimal('theor_bal', 23, 5)->nullable()->comment('Онолын үлдэгдэл');
            $table->decimal('total_bal', 23, 5)->nullable()->comment('Нийт үлд');
            $table->decimal('total_bill', 23, 5)->nullable()->comment('Нийт төлбөр');
            $table->decimal('used_com_bal', 23, 5)->nullable()->comment('');

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
        Schema::dropIfExists('ap_acnt_ln');
    }
};
