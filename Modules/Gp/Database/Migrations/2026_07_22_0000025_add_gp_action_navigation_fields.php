<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gp_action_registry') || Schema::hasColumn('gp_action_registry', 'group_code')) {
            return;
        }

        Schema::table('gp_action_registry', function (Blueprint $table) {
            $table->string('group_code', 80)->nullable()->after('module_code');
            $table->string('group_name', 160)->nullable()->after('group_code');
            $table->string('icon', 80)->nullable()->after('group_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gp_action_registry') || ! Schema::hasColumn('gp_action_registry', 'group_code')) {
            return;
        }

        Schema::table('gp_action_registry', function (Blueprint $table) {
            $table->dropColumn(['group_code', 'group_name', 'icon']);
        });
    }
};
