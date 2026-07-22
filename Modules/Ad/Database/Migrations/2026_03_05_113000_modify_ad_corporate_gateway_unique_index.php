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
        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            // Drop old unique index
            $table->dropUnique('ad_corporate_gateway_banktxndate_bankjrno_instid_bankcode_uniqu');
            
            // Make bankacntno nullable
            $table->string('bankacntno', 22)->nullable()->comment('Банкны дансны дугаар')->change();
            
            // Create new unique index
            $table->unique(['banktxndate', 'bankjrno', 'instid', 'bankcode', 'bankacntno', 'sign', 'txnamount'], 'ad_corporate_gateway_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_corporate_gateway', function (Blueprint $table) {
            // Drop new unique index
            $table->dropUnique('ad_corporate_gateway_unique');
            
            // Revert bankacntno to NOT NULL (if possible, but might fail if null values exist)
            // $table->string('bankacntno', 22)->nullable(false)->comment('Банкны дансны дугаар')->change();
            
            // Restore old unique index
            $table->unique(['banktxndate', 'bankjrno', 'instid', 'bankcode'], 'ad_corporate_gateway_banktxndate_bankjrno_instid_bankcode_uniqu');
        });
    }
};
