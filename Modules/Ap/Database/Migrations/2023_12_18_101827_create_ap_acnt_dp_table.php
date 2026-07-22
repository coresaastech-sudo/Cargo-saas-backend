<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн депозит дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_acnt_dp', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('userid')->nullable()->comment('Хэрэглэгчийн дугаар');
            $table->string('acnt_code', 20)->comment('Дансны дугаар');
            $table->string('name', 300)->nullable()->comment('Нэр');
            $table->string('name2', 300)->nullable()->comment('Нэр2');

            $table->unsignedBigInteger('acnt_manager')->nullable()->comment('Данс хариуцагч');
            $table->string('acnt_type', 20)->nullable()->comment('Дансны төрөл');
            $table->string('acnt_type_name', 50)->nullable()->comment('Дансны төлийн нэр');
            $table->smallInteger('acnt_version')->nullable()->default(1)->comment('Данс хувилбар');
            $table->decimal('acrint_bal', 19, 5)->nullable()->comment('Хуримтлагдсан хүүний дүн');
            $table->decimal('avail_bal', 19, 5)->nullable()->comment('Боломжит үлдэгдэл');
            $table->decimal('avail_limit', 19, 5)->nullable()->comment('Боломжит лимит');
            $table->decimal('block_bal', 23, 5)->nullable()->comment('Битүүмжийн үлдэгдэл');
            $table->smallInteger('blocked_bal')->nullable()->comment('Үлдэгдэл битүүмжлэгдсэн эсэх');
            $table->string('brch_code', 8)->nullable()->comment('Салбарын код');
            $table->string('brch_name', 200)->nullable()->comment('Салбарын нэр');
            $table->string('cap_acnt_code', 20)->nullable()->comment('Хүү олгох данс');
            $table->string('cap_cur_code', 3)->nullable()->comment('Хүү олгох валют');
            $table->decimal('cap_int', 23, 5)->nullable()->comment('Олгох хүү');
            $table->decimal('cap_int2', 23, 5)->nullable()->comment('Олгох хүү2');
            $table->smallInteger('cap_method')->nullable()->default(0)->comment('Хүү олгох арга');
            $table->string('cap_method_name', 50)->nullable()->comment('Хүү олгох арга нэр');
            $table->string('casa_acnt_code', 20)->nullable()->comment('Харилцах данс');
            $table->string('cat_code', 20)->nullable()->comment('Категори код');
            $table->string('cat_name', 200)->nullable()->comment('Категори нэр');
            $table->string('cat_sub_code', 20)->nullable()->comment('Дэд категори код');
            $table->string('cat_sub_name', 200)->nullable()->comment('Дэд категори нэр');
            $table->string('class_name', 50)->nullable()->comment('Ангилал нэр');
            $table->smallInteger('class_no')->nullable()->default(1)->comment('Ангилал');
            $table->smallInteger('closed_by')->nullable()->comment('Хаасан ажилтан код');
            $table->string('closed_by_name', 100)->nullable()->comment('Хаасан ажилтан нэр');
            $table->string('closed_cond', 10)->nullable()->comment('Хаасан шалтгаан');
            $table->date('closed_date')->nullable()->comment('Хаасан огноо');
            $table->string('company_code', 4)->nullable()->comment('Байгууллагын код');
            $table->string('corporate_acnt', 1)->nullable()->default(0)->comment('Байгууллагын данс');
            $table->string('cur_code', 3)->nullable()->comment('Валют');
            $table->decimal('current_bal', 19, 5)->nullable()->comment('Үлдэгдэл');
            $table->string('cust_code', 20)->nullable()->comment('Харилцагчийн код');
            $table->string('cust_name', 200)->nullable()->comment('Харилцагчийн нэр');
            $table->smallInteger('cust_type')->nullable()->comment('Харилцагчийн төрөл');
            $table->string('daily_basis_code', 50)->nullable()->comment('Хүү тооцох өдрийн код');
            $table->smallInteger('do_tran')->nullable()->comment('Гүйлгээ хийгдсэн эсэх');
            $table->date('dormancy_date')->nullable()->comment('Түр идвэхгүй болсон огноо');
            $table->smallInteger('flag_dormant')->nullable()->default(0)->comment('Түр идвэхгүй эсэх');
            $table->smallInteger('flag_frozen')->nullable()->default(0)->comment('Зарцаасан эсэх');
            $table->smallInteger('flag_no_credit')->nullable()->default(0)->comment('Кредитлэх зарцаасан эсэх');
            $table->smallInteger('flag_no_debit')->nullable()->default(0)->comment('Дебитлэх зарцаасан эсэх');
            $table->smallInteger('flag_no_tb')->nullable()->default(0);
            $table->string('flag_no_tb_name', 50)->nullable();
            $table->smallInteger('flag_stopped')->nullable()->default(0)->comment('Зогсоосон эсэх');
            $table->smallInteger('flag_stopped_int')->nullable()->default(0)->comment('Хүү зогсоосон эсэх');
            $table->smallInteger('flag_stopped_payment')->nullable()->default(0)->comment('Төлбөр зогсоосон эсэх');
            $table->smallInteger('get_with_secure')->nullable()->comment('Нууцлах эсэх');
            $table->decimal('int_rate', 23, 5)->nullable()->comment('Хүүний хувь');
            $table->smallInteger('is_allow_partial_liq')->nullable()->comment('');
            $table->smallInteger('is_corp_acnt')->nullable();
            $table->string('is_corp_name', 50)->nullable();
            $table->smallInteger('is_secure')->nullable();
            $table->string('joint_or_single', 1)->nullable()->default('S')->comment('');
            $table->string('joint_or_single_name', 50)->nullable()->comment('');
            $table->date('last_ct_date')->nullable()->comment('Сүүлийн кредит гүйлгээ огноо');
            $table->date('last_dt_date')->nullable()->comment('Сүүлийн дебит гүйлгээ огноо');
            $table->unsignedBigInteger('last_seq_txn')->nullable()->comment('Сүүлийн гүйлгээний дугаар');
            $table->date('last_tb_date')->nullable();
            $table->date('maturity_date')->nullable()->comment('Дуусах огноо');
            $table->string('maturity_option', 1)->nullable()->default('C')->comment('Дуусах нөхцөл');
            $table->string('maturity_option_name', 50)->nullable()->comment('Дуусах нөхцөл нэр');
            $table->decimal('max_amount', 23, 5)->nullable()->default(0)->comment('Хамгийн их дүн');
            $table->decimal('min_amount', 23, 5)->nullable()->default(0)->comment('Хамгийн бага дүн');
            $table->smallInteger('monthly_wd_count')->nullable()->default(0)->comment('Сарын зарлагын тоо');
            $table->string('od_class_name', 50)->nullable()->comment('Улайлтын ангилал нэр');
            $table->smallInteger('od_class_no')->nullable()->comment('Улайлтын ангилал');
            $table->string('od_contract_code', 20)->nullable()->comment('Улайлтын гэрээний дугаар');
            $table->smallInteger('od_flag_wroff')->nullable()->default(0)->comment('');
            $table->smallInteger('od_flag_wroff_int')->nullable()->default(0)->comment('');
            $table->decimal('od_limit', 19, 5)->nullable()->comment('Улайлтын лимит');
            $table->string('od_type', 4)->nullable()->default('NON')->comment('Улайлтын төрөл');
            $table->date('open_date')->nullable()->comment('Нээсэн огноо');
            $table->date('open_date_org')->nullable()->comment('Анх нээсэн огноо');
            $table->smallInteger('passbook_facility')->nullable()->comment('Хад дэвтэр');
            $table->smallInteger('paymt_default')->nullable()->default(0);
            $table->decimal('penalty_rcv', 19, 5)->nullable()->comment('Торгуулын авлага');
            $table->string('prod_code', 12)->nullable()->comment('Бүртээгдэхүүний код');
            $table->string('prod_name', 200)->nullable()->comment('Бүтээгдэхүүний нэр');
            $table->string('rcv_acnt_code', 20)->nullable()->comment('Авлагын данс');
            $table->string('rcv_acnt_name', 200)->nullable()->comment('Авлагын дансны нэр');
            $table->smallInteger('read_bal')->nullable();
            $table->smallInteger('read_name')->nullable();
            $table->smallInteger('read_tran')->nullable();
            $table->string('salary_acnt', 1)->nullable()->default(0);
            $table->string('seg_code', 4)->nullable()->comment('Сегмент код');
            $table->string('seg_name', 200)->nullable()->comment('Сегмент нэр');
            $table->smallInteger('slevel')->nullable()->default(1)->comment('Түвшин');
            $table->date('start_date')->nullable()->comment('Эхлэх огноо');
            $table->string('status', 2)->nullable()->comment('Төлөв');
            $table->string('status_custom', 10)->nullable()->comment('Төлөв өөрчлөсөн');
            $table->date('status_date')->nullable()->comment('Төлвийн огноо');
            $table->string('status_sys', 2)->nullable()->default('N')->comment('Системийн төлөв');
            $table->string('status_sys_name', 20)->nullable()->comment('Сиситемйин төлвийн нэр');
            $table->smallInteger('sys_no')->nullable()->comment('Систем');
            $table->smallInteger('tenor')->nullable();
            $table->string('term_basis', 1)->nullable()->comment('Хугацааны нэгж');
            $table->smallInteger('term_len')->nullable()->default(0)->comment('Хугацаа');
            $table->decimal('total_avail_bal', 19, 5)->nullable()->comment('Нийт боломжит үлд');
            $table->decimal('unex', 19, 5)->nullable()->comment('');
            $table->decimal('unexint_rcv', 19, 5)->nullable()->comment('');
            $table->decimal('unexint_rcv_bill', 19, 5)->nullable()->comment('');

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
        Schema::dropIfExists('ap_acnt_dp');
    }
};
