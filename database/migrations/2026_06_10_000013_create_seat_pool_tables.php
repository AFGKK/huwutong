<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 席位分配记录表
        if (!Schema::hasTable('seat_assignments')) {
            Schema::create('seat_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('license_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('seat_identifier', 100)->comment('席位标识（设备指纹或用户ID等）');
                $table->string('label', 200)->nullable()->comment('席位名称/备注');
                $table->string('status', 20)->default('active')
                    ->comment('active|inactive|waiting');
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('assigned_by', 30)->default('auto')
                    ->comment('auto|admin|api');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['license_id', 'status']);
                $table->index(['license_id', 'seat_identifier']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 排队等待表
        if (!Schema::hasTable('seat_waiting_queue')) {
            Schema::create('seat_waiting_queue', function (Blueprint $table) {
                $table->id();
                $table->foreignId('license_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('seat_identifier', 100)->comment('等待席位标识');
                $table->string('label', 200)->nullable();
                $table->string('device_fingerprint', 200)->nullable();
                $table->string('status', 20)->default('waiting')
                    ->comment('waiting|assigned|cancelled|expired');
                $table->integer('queue_position')->default(0);
                $table->integer('max_wait_minutes')->default(30)->comment('最大等待时间(分钟)');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['license_id', 'status', 'queue_position']);
                $table->index('expires_at');
            });
        }

        // License表添加席位池相关字段
        if (Schema::hasTable('licenses')) {
            if (!Schema::hasColumn('licenses', 'pool_mode')) {
                Schema::table('licenses', function (Blueprint $table) {
                    $table->string('pool_mode', 20)->default('shared')
                        ->after('seats')->comment('shared|exclusive|auto');
                    $table->integer('pool_timeout_minutes')->default(30)
                        ->after('pool_mode')->comment('自动回收超时(分钟)');
                    $table->integer('pool_waiting_limit')->default(50)
                        ->after('pool_timeout_minutes')->comment('排队队列上限');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('licenses')) {
            Schema::table('licenses', function (Blueprint $table) {
                $columns = ['pool_mode', 'pool_timeout_minutes', 'pool_waiting_limit'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('licenses', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('seat_waiting_queue');
        Schema::dropIfExists('seat_assignments');
    }
};
