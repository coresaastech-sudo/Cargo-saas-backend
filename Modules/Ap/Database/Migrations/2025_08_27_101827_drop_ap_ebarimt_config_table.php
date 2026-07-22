<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Арр-н харилцагчийн кредит дансны мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('ap_ebarimt_profile');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ap_ebarimt_profile');
    }
};
