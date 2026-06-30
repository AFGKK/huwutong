<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->unique()->comment('日志类型: audit, security, error, system');
            $table->integer('retention_days')->default(365)->comment('保留天数');
            $table->boolean('is_active')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_retention_policies');
    }
};
