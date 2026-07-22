<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up(): void
    {
        Schema::table(
            'GP_user_access_tokens',
            function (Blueprint $table) {
                $table->index('token');
            }
        );
    }

    public function down(): void {}
};
