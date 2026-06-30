<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_consent_configs', function (Blueprint $table) {
            $table->boolean('show_floating_button')->default(true)->after('layout')->comment('显示浮动 🍪 按钮');
        });
    }

    public function down(): void
    {
        Schema::table('cookie_consent_configs', function (Blueprint $table) {
            $table->dropColumn('show_floating_button');
        });
    }
};
