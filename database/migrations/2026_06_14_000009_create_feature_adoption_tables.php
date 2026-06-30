<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feature_events')) {
            return;
        }
        // 功能使用事件表
        if (!Schema::hasTable('feature_events')) {
            Schema::create('feature_events', function (Blueprint $table) {
                $table->id();
                $table->string('feature_key', 100)->comment('功能标识符');
                $table->string('feature_name', 200)->nullable()->comment('功能名称');
                $table->string('category', 50)->nullable()->comment('功能分类');
                $table->string('action', 50)->default('view')->comment('操作类型: view/click/create/update/delete/export');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('customer_id')->nullable()->comment('关联客户');
                $table->string('session_id', 100)->nullable()->comment('会话ID');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('page_url', 500)->nullable()->comment('页面URL');
                $table->json('metadata')->nullable()->comment('扩展元数据');
                $table->timestamps();

                $table->index(['feature_key', 'created_at']);
                $table->index(['category', 'created_at']);
                $table->index('user_id');
                $table->index('created_at');
            });
        }

        // 功能使用汇总每日快照
        if (!Schema::hasTable('feature_daily_summaries')) {
            Schema::create('feature_daily_summaries', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date');
                $table->string('feature_key', 100);
                $table->string('feature_name', 200)->nullable();
                $table->string('category', 50)->nullable();
                $table->unsignedInteger('pv')->default(0)->comment('页面浏览量/使用次数');
                $table->unsignedInteger('uv')->default(0)->comment('独立用户数');
                $table->unsignedInteger('user_count')->default(0)->comment('使用用户数');
                $table->decimal('adoption_rate', 5, 1)->default(0)->comment('采用率(千分比)');
                $table->timestamps();

                $table->unique(['snapshot_date', 'feature_key']);
                $table->index(['category', 'snapshot_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_daily_summaries');
        Schema::dropIfExists('feature_events');
    }
};
