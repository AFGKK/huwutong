<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_resource_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation')->comment('create/update/delete/batch');
            $table->string('resource_type')->comment('模型类名或表名');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('资源 ID');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role')->nullable()->comment('操作时角色');
            $table->json('payload')->nullable()->comment('请求数据快照');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('allowed')->default(true)->comment('是否允许');
            $table->string('reason')->nullable()->comment('拒绝原因');
            $table->timestamps();

            $table->index('resource_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_resource_operations');
    }
};
