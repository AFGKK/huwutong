<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_fingerprint_history')) {
            return;
        }
        Schema::create('device_fingerprint_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('device_id')->index();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('license_id')->nullable()->index();
            $table->string('fingerprint', 100)->comment('完整指纹字符串');
            $table->unsignedTinyInteger('fingerprint_version')->default(2);
            $table->string('mac', 100)->nullable();
            $table->string('cpu_id', 100)->nullable();
            $table->string('motherboard', 100)->nullable();
            $table->string('disk_sn', 100)->nullable();
            $table->string('system_uuid', 100)->nullable();
            $table->json('components')->nullable()->comment('原始组件JSON');
            $table->string('drift_type', 30)->default('initial')->comment('initial/gradual/partial/major/manual');
            $table->unsignedTinyInteger('changed_components')->default(0)->comment('变更组件数');
            $table->unsignedTinyInteger('total_components')->default(5)->comment('总组件数');
            $table->decimal('similarity_score', 5, 2)->nullable()->comment('与上次指纹的相似度(0-100)');
            $table->boolean('is_baseline')->default(false)->comment('是否为基准指纹');
            $table->boolean('auto_accepted')->default(false)->comment('是否自动接受漂移');
            $table->string('source', 30)->default('activation')->comment('activation/verification/heartbeat/manual');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
            $table->index(['tenant_id', 'drift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprint_history');
    }
};
