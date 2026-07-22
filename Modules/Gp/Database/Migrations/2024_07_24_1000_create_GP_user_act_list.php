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
        Schema::dropIfExists('GP_user_act_list');
        Schema::create('GP_user_act_list', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
            $table->bigInteger('userid')->comment('Хэрэглэгчийн дугаар');
            $table->bigInteger('act_instid')->comment('Холбогдсон байгууллагын дугаар');
            $table->bigInteger('act_userid')->comment('Холбогдсон хэрэглэгчийн дугаар');
            $table->smallInteger('statusid')->default(1)->comment('1 :идэвхитэй, -1 :идэвхигүй');
            $table->unsignedBigInteger('created_by')->comment('Үүсгэсэн хэрэглэгч');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Өөрчилсөн хэрэглэгч');
            $table->timestamps();

            $table->unique(['instid', 'userid', 'act_instid', 'act_userid', 'statusid'], 'unique_instid_userid_act_instid_act_userid_statusid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('GP_user_act_list');
    }
};
