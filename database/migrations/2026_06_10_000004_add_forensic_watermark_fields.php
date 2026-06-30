<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 增强水印表 - 添加暗水印隐写字段
        if (Schema::hasTable('license_watermarks')) {
            Schema::table('license_watermarks', function (Blueprint $table) {
                if (!Schema::hasColumn('license_watermarks', 'forensic_data')) {
                    $table->json('forensic_data')->nullable()->after('watermark_data')
                        ->comment('隐写数据：编码了客户ID+设备指纹+时间戳+GeoIP的加密载荷');
                }
                if (!Schema::hasColumn('license_watermarks', 'embed_type')) {
                    $table->string('embed_type', 30)->default('metadata')->after('embed_location')
                        ->comment('metadata|license_key|integrity_hash|sdk_response');
                }
                if (!Schema::hasColumn('license_watermarks', 'extraction_attempts')) {
                    $table->integer('extraction_attempts')->default(0)->after('status');
                }
                if (!Schema::hasColumn('license_watermarks', 'last_extracted_at')) {
                    $table->timestamp('last_extracted_at')->nullable()->after('extraction_attempts');
                }
                if (!Schema::hasColumn('license_watermarks', 'extracted_by')) {
                    $table->string('extracted_by', 100)->nullable()->after('last_extracted_at');
                }
            });
        }

        // 泄密溯源审计表
        if (!Schema::hasTable('watermark_trace_audits')) {
            Schema::create('watermark_trace_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('watermark_id');
                $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
                $table->string('trace_type', 30)->comment('manual|auto|api|webhook');
                $table->string('source', 100)->nullable()->comment('溯源来源: leaked_url|darkweb|github|internal');
                $table->string('leak_url')->nullable()->comment('泄漏位置URL');
                $table->string('leak_screenshot')->nullable()->comment('泄漏截图');
                $table->json('trace_result')->nullable()->comment('溯源结果');
                $table->string('confidence', 20)->default('medium')->comment('low|medium|high|confirmed');
                $table->text('notes')->nullable();
                $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['watermark_id', 'trace_type']);
                $table->index('confidence');
            });
        }

        // 防篡改策略增加自动恢复动作
        if (Schema::hasTable('tamper_protection_configs')) {
            Schema::table('tamper_protection_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('tamper_protection_configs', 'auto_recovery')) {
                    $table->json('auto_recovery')->nullable()->after('actions')
                        ->comment('自动恢复动作: [{type:"unsuspend_after",seconds:3600},{type:"notify_on_recovery"}]');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('watermark_trace_audits');

        if (Schema::hasTable('license_watermarks')) {
            Schema::table('license_watermarks', function (Blueprint $table) {
                $columns = ['forensic_data', 'embed_type', 'extraction_attempts', 'last_extracted_at', 'extracted_by'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('license_watermarks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('tamper_protection_configs')) {
            Schema::table('tamper_protection_configs', function (Blueprint $table) {
                if (Schema::hasColumn('tamper_protection_configs', 'auto_recovery')) {
                    $table->dropColumn('auto_recovery');
                }
            });
        }
    }
};
