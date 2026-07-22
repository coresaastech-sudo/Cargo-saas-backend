<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Тайлангын параметрийн оролт
     *
     * @return void
     */
    public function up()
    {
        Schema::create('re_inst_report_temp_param_in', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('templateid')->comment('Темп хүснэгтийн id');

            $table->string('name', 128)->comment('Нэр');
            $table->string('name2', 128)->comment('Нэр2')->nullable();
            $table->string('input')->comment('Input variable');
            $table->smallInteger('forminputtype')->comment('Input төрөл: 1 - input-text, 2 - input-number, 3 - text-area, 4 - dropdown, 5 - date, 6 - date range, 7 - Boolean');

            $table->smallInteger('listorder')->comment('Input зурагдах дараалал');

            $table->smallInteger('hasinputcondition')->comment("Ямар нэр оролтын утагаас хамааран гарж ирэх");
            $table->bigInteger('inputid')->comment('Ямар нэр оролтын утагаас хамааралтай бол хамаарах оролтын ID')->nullable();
            $table->text('inputcondition')->comment('Ямар нэр оролтын утагаас хамааралтай бол тухайн оролтын утга дээр тавигдах нөхцөл.')->nullable();

            $table->string('dropdowndic', 10)->nullable()->comment('Dropdown ашигалах dictionary');

            $table->smallInteger('statusid')->comment('Төлөв  -1-устсан, 1-идвэхтэй');
            $table->bigInteger('instid')->comment('Байгууллагын дугаар');
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
        Schema::dropIfExists('re_inst_report_temp_param_in');
    }
};
