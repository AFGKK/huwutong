<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(false)->after('preferences')
                ->comment('D-24: 是否同意接收营销邮件');
            $table->timestamp('marketing_consent_updated_at')->nullable()->after('marketing_opt_in')
                ->comment('D-24: 营销同意时间戳');
            $table->string('locale', 10)->nullable()->after('marketing_consent_updated_at')
                ->comment('D-22: 用户首选语言');
            $table->timestamp('last_active_at')->nullable()->after('locale')
                ->comment('D-24: 最后活跃时间');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_opt_in', 'marketing_consent_updated_at', 'locale', 'last_active_at']);
        });
    }
};
