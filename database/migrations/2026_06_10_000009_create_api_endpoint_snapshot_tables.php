<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API端点快照表 — 记录每个版本每个端点的快照，用于自动检测变更
        if (!Schema::hasTable('api_endpoint_snapshots')) {
            Schema::create('api_endpoint_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('api_version_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('endpoint_id')->nullable()->comment('FK -> api_doc_endpoints');
                $table->string('method', 10);
                $table->string('path', 500);
                $table->string('group', 100)->nullable();
                $table->string('tag', 100)->nullable();
                $table->string('summary', 500)->nullable();
                $table->string('status', 30)->default('active')->comment('endpoint status at snapshot time');
                $table->json('parameters_snapshot')->nullable()->comment('参数快照');
                $table->json('responses_snapshot')->nullable()->comment('响应快照');
                $table->string('snapshot_version', 30)->comment('快照所属版本号');
                $table->timestamp('snapshot_at')->index()->comment('快照时间');
                $table->timestamps();

                $table->index(['api_version_id', 'method', 'path']);
                $table->index('snapshot_version');
            });

            // 添加外键（延迟执行确保表已创建）
            try {
                Schema::table('api_endpoint_snapshots', function (Blueprint $table) {
                    $table->foreign('endpoint_id')->references('id')->on('api_doc_endpoints')->nullOnDelete();
                });
            } catch (\Exception $e) {
                // 如果api_doc_endpoints表不存在，跳过外键
            }
        }

        // 给api_changelogs表添加来源标记（如果表已存在）
        if (Schema::hasTable('api_changelogs')) {
            if (!Schema::hasColumn('api_changelogs', 'source')) {
                Schema::table('api_changelogs', function (Blueprint $table) {
                    $table->string('source', 20)->default('manual')
                        ->after('migration_guide')
                        ->comment('manual|auto_detect|scan');
                });
            }

            if (!Schema::hasColumn('api_changelogs', 'snapshot_id')) {
                Schema::table('api_changelogs', function (Blueprint $table) {
                    $table->unsignedBigInteger('snapshot_id')->nullable()->after('source');
                    $table->index('snapshot_id');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('api_changelogs', function (Blueprint $table) {
            $table->dropForeign(['snapshot_id']);
            $table->dropColumn(['source', 'snapshot_id']);
        });
        Schema::dropIfExists('api_endpoint_snapshots');
    }
};
