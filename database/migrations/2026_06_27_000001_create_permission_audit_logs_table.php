<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permission_audit_logs')) {
            Schema::create('permission_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action');
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_name')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }

        if (!Schema::hasTable('role_hierarchy')) {
            Schema::create('role_hierarchy', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->foreignId('parent_role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['role_id', 'parent_role_id']);
                $table->index('parent_role_id');
            });
        }

        if (!Schema::hasTable('role_templates')) {
            Schema::create('role_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('category')->default('custom');
                $table->json('permissions')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_templates');
        Schema::dropIfExists('role_hierarchy');
        Schema::dropIfExists('permission_audit_logs');
    }
};
