<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sdk_integrity_checks')) {
            return;
        }
        // SDK完整性检查记录
        Schema::create('sdk_integrity_checks', function (Blueprint $table) {
            $table->id();
            $table->string('sdk_instance_id', 64)->comment('SDK实例唯一标识');
            $table->string('language', 20)->comment('php/node/python/go/java');
            $table->string('sdk_version', 20)->comment('SDK版本号');
            $table->string('machine_id', 64)->nullable()->comment('机器标识');
            $table->boolean('passed')->default(false)->comment('是否通过校验');
            $table->json('file_checksums')->nullable()->comment('各文件哈希值');
            $table->json('failed_files')->nullable()->comment('校验失败的文件列表');
            $table->text('error_message')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index('sdk_instance_id');
            $table->index(['language', 'sdk_version']);
            $table->index('passed');
            $table->index('checked_at');
        });

        // SDK远程销毁命令
        Schema::create('sdk_destroy_commands', function (Blueprint $table) {
            $table->id();
            $table->string('command_id', 64)->unique()->comment('命令唯一标识');
            $table->string('sdk_instance_id', 64)->nullable()->comment('目标SDK实例（null=全部）');
            $table->string('language', 20)->nullable()->comment('目标语言（null=全部）');
            $table->string('version_constraint', 50)->nullable()->comment('版本约束，如 <=1.0.0');
            $table->string('destroy_mode', 20)->default('soft')->comment('soft/hard');
            $table->string('trigger_type', 40)->comment('触发类型: integrity_failure/remote_command/license_revoked/device_blacklisted/version_deprecated');
            $table->text('reason')->nullable()->comment('销毁原因');
            $table->string('status', 20)->default('pending')->comment('pending/dispatched/confirmed/expired/cancelled');
            $table->json('dispatched_instances')->nullable()->comment('已下发的SDK实例列表');
            $table->json('confirmed_instances')->nullable()->comment('已确认执行的SDK实例列表');
            $table->integer('affected_count')->default(0)->comment('预估影响SDK实例数');
            $table->timestamp('expires_at')->nullable()->comment('命令过期时间');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('trigger_type');
            $table->index('sdk_instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdk_destroy_commands');
        Schema::dropIfExists('sdk_integrity_checks');
    }
};
