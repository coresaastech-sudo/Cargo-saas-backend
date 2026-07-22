<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н хэрэглэгчийн банкны дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ap_cust_bank_token', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->unsignedBigInteger('cust_user_id')->comment('Хэрэглэгчийн дугаар');
            $table->string('customerregisterid', 10)->nullable()->comment('NEGDI системд бүртгэгдсэн харилцагчийн дугаар');
            $table->string('tokenid', 60)->nullable()->comment('NEGDI системд бүртгэсэн token ID. Token гүйлгээнд ашиглах дугаар');
            $table->string('maskedpan', 22)->nullable()->comment('Харилцагчийн картын дугаар нууцлагдсан хэлбэрээр. Харилцагчид харуулах зорилготой');
            $table->string('expdate', 10)->nullable()->comment('Харилцагчийн картын хүчинтэй хугацаа. Харилцагчид харуулах зорилготой');
            $table->string('brand', 20)->nullable()->comment('Token ID-д харгалзах картын брэнд');
            $table->string('bankname', 30)->nullable()->comment('Token ID-д харгалзах картын банкны нэрний код (Зөвхөн дотоодын банкны нэрний код).');

            $table->smallInteger('statusid')->default(1)->comment('Төлөв');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();
            $table->unique(['cust_user_id', 'tokenid', 'maskedpan', 'expdate', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_cust_bank_token');
    }
};
