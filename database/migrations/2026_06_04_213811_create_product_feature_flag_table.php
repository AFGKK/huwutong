<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_feature_flag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('feature_flag_id')->constrained('feature_flags')->onDelete('cascade');
            $table->boolean('is_active')->default(true)->comment('该产品下该功能的开关');
            $table->timestamps();

            $table->unique(['product_id', 'feature_flag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_feature_flag');
    }
};
