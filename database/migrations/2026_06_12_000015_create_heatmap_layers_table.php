<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heatmap_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('data_source', 50)->comment('license_activations/product_usage/api_calls/revenue');
            $table->string('type', 50)->default('heatmap_scatter')->comment('heatmap_scatter/country_choropleth/region_bubble');
            $table->json('config')->nullable()->comment('颜色、透明度、大小等配置');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'data_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heatmap_layers');
    }
};
