<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('color', 7)->nullable()->comment('CSS hex color like #409eff');
            $table->string('group', 50)->nullable()->comment('logical grouping: status, priority, custom, etc.');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('system tags cannot be deleted');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable'); // taggable_id + taggable_type (自动创建索引)
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            // prevent duplicate tag on same entity
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
