<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhook_filters')) {
            Schema::create('webhook_filters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_endpoint_id')->constrained()->onDelete('cascade');
                $table->string('name', 100)->comment('过滤器名称');
                $table->json('conditions')->comment('条件列表: [{field, operator, value}]');
                $table->string('match_type', 10)->default('all')->comment('all=AND, any=OR');
                $table->json('payload_template')->nullable()->comment('自定义Payload映射模板');
                $table->boolean('is_active')->default(true);
                $table->integer('priority')->default(0)->comment('优先级, 越大越先');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['webhook_endpoint_id', 'is_active']);
                $table->index('priority');
            });
        }

        // webhook_endpoints 表增加 filter_mode 字段
        if (Schema::hasTable('webhook_endpoints')) {
            if (!Schema::hasColumn('webhook_endpoints', 'filter_mode')) {
                Schema::table('webhook_endpoints', function (Blueprint $table) {
                    $table->string('filter_mode', 20)->default('none')
                        ->after('events')
                        ->comment('none=不过滤, all=全部匹配, any=任一匹配');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('webhook_endpoints')) {
            Schema::table('webhook_endpoints', function (Blueprint $table) {
                if (Schema::hasColumn('webhook_endpoints', 'filter_mode')) {
                    $table->dropColumn('filter_mode');
                }
            });
        }
        Schema::dropIfExists('webhook_filters');
    }
};
