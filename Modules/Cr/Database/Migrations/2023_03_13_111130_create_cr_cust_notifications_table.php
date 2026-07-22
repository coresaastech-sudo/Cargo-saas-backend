<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Илгээж буй мэдэгдэл харилцагчтай холбож буй бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('cr_cust_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custid')->comment('Харилцагчийн ID');
            $table->unsignedBigInteger('notification_id')->comment('Мэдэгдлийн ID');
            $table->smallInteger('is_read')->default(0)->comment('Харилцагч уншсан эсэх 1-тийм, 0-үгүй');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв, 0-идэвхгүй, 1-идэвхтэй');
            $table->string('custtype', 10)->default('')->comment(' Мэдэгдэл илгээсэн харилцагчийн төрөл /admin, meapp, org, cust./');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
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
        Schema::dropIfExists('cr_cust_notifications');
    }
};
