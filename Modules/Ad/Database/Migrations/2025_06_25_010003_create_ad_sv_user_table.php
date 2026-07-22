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
        Schema::create('ad_sv_user', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('userid')->comment('Хэрэглэгчийн ID');
            $table->bigInteger('svuserid')->comment('Супервайзор хэрэглэгчийн ID');
            $table->smallInteger('svtype')->default(1)->comment('Супервайзорын төрөл 0 - Хянуулах буюу Офлайн, 1 - Онлайн');
            $table->smallInteger('statusid')->default(1)->comment('Төлөв, 1-идвэхтэй, -1...-идэвхгүй');
            $table->bigInteger('instid')->comment('Байгууллагын id');
            $table->unsignedBigInteger('created_by')->comment('Бүртгэсэн хэрэглэгчийн id');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчлөсөн хэрэглэгчийн id');
            $table->timestamps();

            $table->unique(['instid', 'statusid', 'userid', 'svuserid', 'svtype']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_sv_user');
    }
};
