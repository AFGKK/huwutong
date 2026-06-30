<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // offline_activations 表添加 CRL 补全验证字段
        if (Schema::hasTable('offline_activations')) {
            Schema::table('offline_activations', function (Blueprint $table) {
                if (! Schema::hasColumn('offline_activations', 'crl_verified_at')) {
                    $table->timestamp('crl_verified_at')->nullable()->after('expires_at')
                        ->comment('CRL 网络恢复补全验证时间');
                }
                if (! Schema::hasColumn('offline_activations', 'crl_result')) {
                    $table->string('crl_result', 20)->nullable()->after('crl_verified_at')
                        ->comment('CRL 补全验证结果: clean/revoked');
                }
            });
        }

        // offline_verifications 表添加 CRL 补全验证字段
        if (Schema::hasTable('offline_verifications')) {
            Schema::table('offline_verifications', function (Blueprint $table) {
                if (! Schema::hasColumn('offline_verifications', 'crl_verified_at')) {
                    $table->timestamp('crl_verified_at')->nullable()->after('client_ip')
                        ->comment('CRL 网络恢复补全验证时间');
                }
                if (! Schema::hasColumn('offline_verifications', 'crl_result')) {
                    $table->string('crl_result', 20)->nullable()->after('crl_verified_at')
                        ->comment('CRL 补全验证结果: clean/revoked');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offline_activations')) {
            Schema::table('offline_activations', function (Blueprint $table) {
                $table->dropColumn(['crl_verified_at', 'crl_result']);
            });
        }
        if (Schema::hasTable('offline_verifications')) {
            Schema::table('offline_verifications', function (Blueprint $table) {
                $table->dropColumn(['crl_verified_at', 'crl_result']);
            });
        }
    }
};
