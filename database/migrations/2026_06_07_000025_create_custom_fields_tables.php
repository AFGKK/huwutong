<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 自定义字段定义
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('field_type', 30)->default('text'); // text, textarea, number, select, multi_select, date, boolean
            $table->json('options')->nullable()->comment('For select/multi_select: available options');
            $table->string('placeholder', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('group', 100)->nullable()->comment('Field group for UI organization');
            $table->string('default_value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // License 自定义字段值
        Schema::create('license_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id')->index();
            $table->unsignedBigInteger('field_definition_id')->index();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'field_definition_id'], 'license_field_unique');
            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
            $table->foreign('field_definition_id')->references('id')->on('custom_field_definitions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
