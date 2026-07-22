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
        Schema::dropIfExists('ap_negdi');
        Schema::create('ap_negdi', function (Blueprint $table) {
            $table->id()->comment('ID дугаар');
            $table->string('ordertype', 30)->nullable()->comment('Худалдан авалтын гүйлгээ (CofPayment – Төлбөр төлөх гүйлгээ)');
            $table->string('terminalid', 15)->nullable()->comment('NEGDI-гээс өгөгдөх терминал дугаар (21 – Төлбөр төлөх гүйлгээ)');
            $table->string('username', 30)->nullable()->comment('NEGDI-гээс өгөгдөх логин нэр (TerminalSys/user2 – Төлбөр төлөх гүйлгээ)');
            $table->string('password', 50)->nullable()->comment('NEGDI-гээс өгөгдөх нууц үг');
            $table->string('returnurl', 255)->nullable()->comment('Гүйлгээ хийгдэж дууссан дараа дуудагдах хаяг');
            $table->decimal('amount', 23, 5)->comment('Гүйлгээ хийгдэх үнийн дүн');
            $table->string('cur_code', 3)->nullable()->comment('Гүйлгээний валют');
            $table->string('customerid', 30)->nullable()->comment('Мерчант дээрх харилцагчийн бүртгэлийн дугаар (дахин давтагдашгүй)');
            $table->string('customername', 50)->nullable()->comment('Мерчант дээрх харилцагчийн бүртгэлтэй нэр (50 хүртэл тэмдэгт)');
            $table->string('description', 50)->nullable()->comment('Гүйлгээний утга (50 хүртэл тэмдэгт))');

            $table->string('checkid', 16)->comment('NEGDI систем дээрх гүйлгээний шалгах дугаар. Үндсэн гүйлгээний хариунд ирсэн.');
            $table->string('tranid', 45)->nullable()->comment('NEGDI систем дээрх гүйлгээний дугаар. Буцаалтын гүйлгээнд ашиглагдана.');
            $table->string('status', 20)->nullable()->comment('Гүйлгээний үр дүн. Амжилттай бол Approved, Амжилтгүй бол Declined гэсэн утга буцна.');
            $table->string('approvalCode', 20)->nullable()->comment('Амжилттай болсон гүйлгээний Approval code');
            $table->string('tranActionId', 40)->nullable()->comment('tranActionId');
            $table->string('ridByPmo', 40)->nullable()->comment('ridByPmo');
            $table->string('customerregisterid', 10)->nullable()->comment('NEGDI системд бүртгэгдсэн харилцагчийн дугаар');
            $table->string('tokenid', 60)->nullable()->comment('NEGDI системд бүртгэсэн token ID. Token гүйлгээнд ашиглах дугаар');
            $table->string('maskedpan', 22)->nullable()->comment('Харилцагчийн картын дугаар нууцлагдсан хэлбэрээр. Харилцагчид харуулах зорилготой');
            $table->string('expdate', 10)->nullable()->comment('Харилцагчийн картын хүчинтэй хугацаа. Харилцагчид харуулах зорилготой');
            $table->string('detail', 100)->nullable()->comment('Амжилтгүй болсон гүйлгээний дэлгэрэнгүй');
            $table->string('negdiurl')->nullable()->comment('CALLBACK_URL Картын мэдээлэл оруулах URL ирнэ');

            $table->date('callbacked_at')->nullable()->comment('CALLBACK_URL дуудагдсан огноо');
            $table->unsignedBigInteger('jrno')->nullable()->comment('Дотоод гүйлгээний дугаар');
            $table->string('typeid', 10)->nullable()->comment('Дотоод гүйлгээний дансны төрлийн дугаар');
            $table->string('to_account', 20)->nullable()->comment('Гүйлгээ хийгдэх данс (Данс цэнэглэх, зээл төлөх ...)');
            $table->string('txn_type', 2)->nullable()->comment('Гүйлгээний төрөл');

            $table->unsignedBigInteger('instid')->comment('Байгууллагын дугаар');
            $table->smallInteger('statusid')->comment('Бүртгэлийн төлөв');
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
        Schema::dropIfExists('ap_negdi');
    }
};
