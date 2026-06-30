<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 可视化工作流设计
        Schema::create('workflow_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->default('general')->comment('general/approval/license/billing/notification');
            $table->json('canvas_config')->nullable()->comment('画布缩放/偏移/布局');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 30)->default('draft')->comment('draft/published/archived');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'status']);
        });

        // 工作流节点
        Schema::create('workflow_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained('workflow_designs')->cascadeOnDelete();
            $table->string('node_id', 80)->comment('前端唯一标识如 node_1');
            $table->string('type', 50)->comment('trigger/condition/action/approval/webhook/end');
            $table->string('label', 200);
            $table->string('icon', 50)->nullable();
            $table->json('config')->nullable()->comment('节点配置');
            $table->json('position')->nullable()->comment('画布坐标 {x,y}');
            $table->json('style')->nullable()->comment('颜色/大小');
            $table->json('input_schema')->nullable()->comment('输入参数定义');
            $table->json('output_schema')->nullable()->comment('输出参数定义');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['design_id', 'node_id']);
        });

        // 工作流连线
        Schema::create('workflow_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained('workflow_designs')->cascadeOnDelete();
            $table->string('edge_id', 80)->comment('前端唯一标识如 edge_1');
            $table->string('source_node', 80)->comment('起点 node_id');
            $table->string('target_node', 80)->comment('终点 node_id');
            $table->string('source_handle', 50)->nullable()->comment('起点端口');
            $table->string('target_handle', 50)->nullable()->comment('终点端口');
            $table->string('label', 200)->nullable()->comment('条件标签');
            $table->string('condition_type', 50)->nullable()->comment('success/failure/conditional');
            $table->json('condition_config')->nullable();
            $table->string('line_style', 30)->default('solid')->comment('solid/dashed/dotted');
            $table->string('color', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['design_id', 'edge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_edges');
        Schema::dropIfExists('workflow_nodes');
        Schema::dropIfExists('workflow_designs');
    }
};
