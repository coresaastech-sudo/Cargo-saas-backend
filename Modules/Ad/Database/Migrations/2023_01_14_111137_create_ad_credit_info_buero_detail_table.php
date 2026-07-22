<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ЗМС мэдээ нийлүүлэлтийн дэлгэрэнгүй
     * @return void
     */
    public function up()
    {
        Schema::create('ad_credit_info_buero_detail', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('buero_id')->nullable()->comment('Багц мэдээ ID');
            $table->string('custno', 20)->comment('Харилцагчийн дугаар');
            $table->string('acntno', 20)->comment('Зээлийн дансны дугаар');
            $table->string('loancode', 30)->nullable()->comment('Зээлийн код ЗМС талд ашиглаж буй код');
            $table->smallInteger('status')->comment('Зээлийн дансны төлөв');
            $table->smallInteger('type')->comment('Зээлийн төрөл 1 - Зээлийн шугам, 2 - Зээл, 3 - Авлага');
            $table->string('action', 10)->comment('Үйлдэл add-нэмэх, update - засварлах');
            $table->decimal('advamount', 23, 8)->comment('Олгосон дүн');
            $table->dateTime('starteddate')->comment('Эхлэх хугацаа');
            $table->date('expiredate')->nullable()->comment('Хүчингүй болох хугацаа');
            $table->string('curcode', 3)->comment('Валют');
            $table->decimal('balance', 20, 2)->comment('Зээлийн үлдэгдэл');
            $table->date('extdate')->nullable()->comment('Зээл авсан сүүлчийн огноо');
            $table->smallInteger('interestinperc')->comment('Зээлийн төлөгдөх хүүгийн хувь');
            $table->smallInteger('commissionperc')->comment('Зээлийн төлөгдөх хураамжийн хувь');
            $table->string('sectorcode', 3)->comment('Шугамын зээлийн зориулалт');
            $table->decimal('fee', 12, 2)->comment('Зээлийн төлөгдөх хураамжийн хэмжээ');
            $table->string('loanclasscode', 2)->comment('Зээлийн ангилал');
            $table->smallInteger('isapproved')->comment('Зээлдэгчийн зөвшөөрөл авагдсан эсэх Зөвшөөрсөн бол: 1, Зөвшөөрөөгүй бол: 0');

            /// Line related (only)
            $table->string('linetype', 2)->nullable()->comment('Шугамын төрөл');
            $table->smallInteger('loaninterest')->nullable()->comment('Зээлийн хүүгийн хувь');
            $table->smallInteger('timestoloan')->nullable()->comment('Зээл авсан тоо');

            /// Loan related (only)
            $table->string('loanprovenance', 2)->nullable()->comment('Зээлийн үүсэл');
            $table->smallInteger('loan_additional_interest')->nullable()->comment('Нэмэгдүүлсэн хүүгийн хувь');
            $table->string('loanintype', 3)->nullable()->comment('Зээлийн төрөл');

            /// Receivable related (only)
            $table->string('receivabletype', 2)->nullable()->comment('Авлагын төрөл');
            $table->string('loan_contract_no', 20)->default('')->comment('Гэрээний дугаар');
            $table->date('loan_contract_date')->nullable()->comment('Гэрээ байгуулсан огноо');
            $table->string('loan_contract_change_reason', 100)->nullable()->comment('Өөрчилсөн шалтгаан');
            $table->decimal('loan_int_balance', 20, 2)->default(0)->comment('Хүүгийн өр төгрөгөөр');
            $table->decimal('loan_additional_int_balance', 20, 2)->default(0)->comment('Нэмэгдүүлсэн хүүгийн өр төгрөгөөр');
            $table->smallInteger('loan_additional_interest')->nullable()->comment('Нэмэгдүүлсэн хүүгийн хувь');
            $table->date('loan_paid_date')->nullable()->comment('Төлөгдөж дууссан огноо');
            $table->string('loan_decide_status', 2)->nullable()->comment('Зээлдүүлэгч үүргийг хэрхэн шийдвэрлэсэн эсэх');
     
            $table->smallInteger('statusid')->default(1)->comment('Төлөв -1 - устсан, 1 - идэвхтэй, 2 - ЗМС нийлүүлсэн, 3 - ЗМС амжилтгүй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
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
        Schema::dropIfExists('ad_auto_job');
    }
};
