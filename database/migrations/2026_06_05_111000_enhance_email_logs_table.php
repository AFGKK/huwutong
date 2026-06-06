<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete()->after('id');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            $table->text('bounce_reason')->nullable()->after('bounced_at');
            $table->string('tracking_id', 64)->nullable()->unique()->after('bounce_reason');
            $table->ipAddress('opened_ip')->nullable()->after('tracking_id');
            $table->string('user_agent')->nullable()->after('opened_ip');
            $table->string('click_url')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id', 'clicked_at', 'bounced_at', 'bounce_reason',
                'tracking_id', 'opened_ip', 'user_agent', 'click_url',
            ]);
        });
    }
};
