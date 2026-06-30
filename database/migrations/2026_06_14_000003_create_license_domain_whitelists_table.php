<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('license_domain_whitelists')) {
            return;
        }
        if (!Schema::hasTable('license_domain_whitelists')) {
            Schema::create('license_domain_whitelists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('license_id')->constrained()->onDelete('cascade');
                $table->string('domain', 255)->comment('域名, 如 example.com 或 *.example.com');
                $table->boolean('is_wildcard')->default(false)->comment('是否通配符域名');
                $table->string('scope', 30)->default('both')->comment('activation/validation/both');
                $table->string('status', 20)->default('active')->comment('active/inactive/pending');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['license_id', 'status']);
                $table->index('domain');
            });
        }

        if (!Schema::hasTable('license_domain_validation_logs')) {
            Schema::create('license_domain_validation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('license_id');
                $table->string('domain', 255)->comment('请求来源域名');
                $table->string('result', 20)->comment('passed/blocked');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamps();

                $table->foreign('license_id')->references('id')->on('licenses')->onDelete('cascade');
                $table->index(['license_id', 'created_at']);
                $table->index('result');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_domain_validation_logs');
        Schema::dropIfExists('license_domain_whitelists');
    }
};
