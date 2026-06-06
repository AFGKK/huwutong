<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_chain_anchors', function (Blueprint $table) {
            $table->id();
            $table->string('root_hash', 64)->unique()->comment('当前 Merkle 根哈希');
            $table->string('prev_root_hash', 64)->nullable()->comment('上一个根哈希（形成链）');
            $table->timestamp('anchored_at')->comment('锚定时间');
            $table->string('anchor_type', 20)->default('database')->comment('锚定方式: database / blockchain / transparency_log');
            $table->string('anchor_ref', 255)->nullable()->comment('锚定引用（如区块链交易ID/透明度日志URI）');
            $table->bigInteger('log_count')->default(0)->comment('锚定时审计日志总数');
            $table->bigInteger('from_log_id')->nullable()->comment('批次起始日志ID');
            $table->bigInteger('to_log_id')->nullable()->comment('批次结束日志ID');
            $table->json('metadata')->nullable()->comment('附加元数据');
            $table->timestamps();

            $table->index('anchored_at');
        });

        // 给 logs 表添加 Merkle 相关字段
        Schema::table('logs', function (Blueprint $table) {
            $table->string('merkle_hash', 64)->nullable()->unique()->after('user_agent')
                ->comment('该日志记录的 Merkle 哈希');
            $table->bigInteger('merkle_parent_id')->nullable()->after('merkle_hash')
                ->comment('前一条日志 ID（哈希链）');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn(['merkle_hash', 'merkle_parent_id']);
        });
        Schema::dropIfExists('audit_chain_anchors');
    }
};
