<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('fingerprint')->comment('设备指纹');
            $table->string('platform')->nullable()->comment('平台');
            $table->string('os_version')->nullable()->comment('操作系统版本');
            $table->integer('trust_score')->default(0)->comment('信任分');
            $table->boolean('is_blacklisted')->default(false)->comment('是否黑名单');
            $table->boolean('is_virtual')->default(false)->comment('是否虚拟环境');
            $table->json('metadata')->nullable()->comment('扩展信息');
            $table->timestamp('last_seen_at')->nullable()->comment('最后出现时间');
            $table->timestamps();

            $table->index(['tenant_id', 'fingerprint']);
            $table->index(['license_id', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
