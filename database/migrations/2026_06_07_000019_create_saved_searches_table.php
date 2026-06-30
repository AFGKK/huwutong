<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('page', 50)->comment('licenses|customers|tickets|products');
            $table->json('filters')->comment('saved filter parameters');
            $table->boolean('is_shared')->default(false)->comment('shared with team');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
