<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('utm_tracking_records')) {
            Schema::create('utm_tracking_records', function (Blueprint $table) {
                $table->id();
                $table->nullableMorphs('trackable'); // user/customer/lead
                $table->string('session_id', 100)->nullable()->index()->comment('浏览器会话ID');
                $table->string('utm_source', 100)->nullable();
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 100)->nullable();
                $table->string('utm_term', 200)->nullable();
                $table->string('utm_content', 200)->nullable();
                $table->string('landing_page', 500)->nullable()->comment('落地页URL');
                $table->string('referrer_url', 500)->nullable()->comment('来源页URL');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('channel_group', 50)->nullable()->comments('渠道分组');
                $table->string('attribution_type', 30)->default('first_visit')->comment('first_visit/conversion');
                $table->timestamps();

                $table->index(['utm_source', 'utm_medium', 'utm_campaign']);
                $table->index('created_at');
            });
        }

        // 在 users 表增加 first_utm 字段
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumn('users', 'first_utm_source')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('first_utm_source', 100)->nullable()->after('remember_token');
                    $table->string('first_utm_medium', 100)->nullable()->after('first_utm_source');
                    $table->string('first_utm_campaign', 100)->nullable()->after('first_utm_medium');
                    $table->timestamp('first_utm_landed_at')->nullable()->after('first_utm_campaign');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = ['first_utm_source', 'first_utm_medium', 'first_utm_campaign', 'first_utm_landed_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('utm_tracking_records');
    }
};
