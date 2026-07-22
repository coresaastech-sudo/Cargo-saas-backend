<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     * Арр-н харилцагчийн гэрээний мэдээллийн бүртгэл
     * @return void
     */
    public function up()
    {
        Schema::create('ad_cgw_txn_desc_combination', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('value', 40)->comment('Гүйлгээний утга');
            $table->string('prodcode', 10)->comment('Гүйлгээний тайлбарын харгалзах бүтээгдэхүүний код');
            $table->string('name', 50)->comment('Гүйлгээний тайлбарын нэр');
            $table->string('name2', 50)->nullable()->comment('Гүйлгээний тайлбарын нэр 2');
            $table->smallInteger('statusid')->comment('Төлөвийн код, 1-идэвхтэй, 0-идэвхгүй');
            $table->bigInteger('instid');
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['value', 'prodcode', 'instid', 'statusid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ad_cgw_txn_desc_combination');
    }
};
