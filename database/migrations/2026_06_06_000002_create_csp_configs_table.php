<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csp_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('配置名称');
            $table->boolean('is_active')->default(true);
            $table->string('mode')->default('enforce')->comment('enforce 或 report-only');
            $table->json('directives')->comment('CSP 指令字典，如 {"default-src":["\'self\'"], "script-src":["\'self\'","\'unsafe-inline\'"]}');
            $table->string('route_pattern')->nullable()->comment('路由匹配模式');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('csp_violations', function (Blueprint $table) {
            $table->id();
            $table->string('document_uri')->nullable();
            $table->string('blocked_uri')->nullable();
            $table->string('violated_directive')->nullable();
            $table->string('effective_directive')->nullable();
            $table->string('source_file')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->unsignedInteger('column_number')->nullable();
            $table->unsignedInteger('status_code')->nullable();
            $table->text('original_policy')->nullable();
            $table->text('disposition')->nullable()->comment('report 或 enforce');
            $table->string('user_agent')->nullable();
            $table->ipAddress('reported_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csp_violations');
        Schema::dropIfExists('csp_configs');
    }
};
