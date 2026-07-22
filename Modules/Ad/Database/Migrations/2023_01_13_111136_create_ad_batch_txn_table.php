<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ad_batch_txn', function (Blueprint $table) {
            $table->id();
            $table->string('fileid', 255);
            $table->string('filename', 255);
            $table->unsignedMediumInteger('txncount')->nullable()->comment('Багцаар орж ирсэн гүйлгээний тоо');
            $table->unsignedMediumInteger('txnsuccesscount')->nullable()->comment('Багцаар орж ирсэн гүйлгээний амжилттай тоо');
            $table->unsignedMediumInteger('txnerrorcount')->nullable()->comment('Багцаар орж ирсэн гүйлгээний алдаатай тоо');
            $table->smallInteger('process')->default(0)->comment('0 - pending, 1 - finished, 2 - processing, 3 - stopped');
            $table->smallInteger('statusid')->default(0)->comment('Төлөв -1 - устсан, 1 - идэвхтэй, 0 - идэвхгүй');
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
        Schema::dropIfExists('ad_notifications');
    }
};
