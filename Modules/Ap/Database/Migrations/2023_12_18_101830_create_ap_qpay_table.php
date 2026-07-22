<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн гэрээний мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_qpay', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('sender_invoice_no', 45)->nullable()->comment('Системээс үүсгэгдэх давтагдашгүй дугаар');
            $table->string('invoice_receiver_code', 45)->nullable()->comment('Байгууллагын нэхэмжлэхийг хүлээн авч буй харилцагчийн дахин давтагдашгүй дугаар');
            $table->string('invoice_description')->nullable()->comment('Гүйлгээний утга');
            $table->decimal('amount', 23, 5)->nullable()->comment('Гүйлгээ хийгдэх үнийн дүн');
            $table->string('cur_code')->nullable()->nullable()->comment('Валют');
            $table->string('callback_url')->nullable()->comment('Гүйлгээ хийгдэж дууссан дараа дуудагдах хаяг');
            $table->string('invoice_id', 50)->nullable()->comment('QPAY-s үүсгэгдэх гүйлгээний давтагдашгүй дугаар');
            $table->string('qr_text', 800)->nullable()->comment('QPAY-s үүсгэгдэх гүйлгээний мэдээллийг агуулсан String');
            $table->string('qpay_shorturl', 500)->nullable()->comment('QPAY-s үүсгэгдэх URL');
            $table->date('callbacked_at',)->nullable()->comment('CALLBACK_URL дуудагдсан огноо');
            $table->decimal('checked_paid_amount', 23, 5)->nullable()->comment('Гүйлгээ хийгдэх үнийн дүн');
            $table->biginteger('checked_count')->nullable()->comment('Гүйлгээний тоо');
            $table->string('checked_rows')->nullable()->comment('Гүйлгээтэй холбоотой мэдээллийг банкнаас татаж хадгална');
            $table->date('checked_date')->nullable()->comment('Гүйлгээний мэдэээлэл шалгасан огноо');
            $table->biginteger('jrno')->nullable()->comment('Түр дансаар хийгдэх гүйлгээний журналын дугаар');
            $table->string('typeid', 10)->nullable()->comment('Дотоод гүйлгээний дансны төрлийн дугаар');
            $table->string('to_account')->nullable()->comment('Нэхэмжлэхийн гүйлгээ хийгдэх данс (Данс цэнэглэх ...)');
            $table->string('txn_type', 2)->nullable()->comment('Нэхэмжлэх үүсгэсэн төрөл,
            01 - Лавлагаа
            02 - Данс цэнэглэх
            03 - Нэхэмжлэх төлөх');
            $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй');
            $table->bigInteger('instid');
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
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
        Schema::dropIfExists('ap_qpay');
    }
};
