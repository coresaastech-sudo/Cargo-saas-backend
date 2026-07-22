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
        Schema::create('GP_inst_user_ip', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->bigInteger('userid')->comment('Хэрэглэгчийн ID');
            $table->string('ip_address', 50)->comment('IP хаяг');
            $table->smallInteger('statusid')->comment('Төлөв 1 - Идэвхтэй, 0 - Идэвхгүй');
            $table->unsignedBigInteger('created_by')->comment('Бүртэгсэн ажилтан');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн ажилтан');
            $table->timestamps();

            $table->unique(['ip_address', 'userid', 'instid', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_inst_user_ip');
    }
};
