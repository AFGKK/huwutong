<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false)->comment('是否启用维护模式');
            $table->string('title', 200)->nullable()->comment('维护标题');
            $table->text('message')->nullable()->comment('维护公告');
            $table->json('whitelist_ips')->nullable()->comment('白名单IP');
            $table->json('whitelist_paths')->nullable()->comment('白名单路径，如 /api/health/*');
            $table->timestamp('scheduled_end_at')->nullable()->comment('预计恢复时间');
            $table->timestamp('auto_disable_at')->nullable()->comment('自动关闭维护模式时间');
            $table->unsignedTinyInteger('retry_after')->default(60)->comment('Retry-After 秒数，默认60');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_configs');
    }
};
