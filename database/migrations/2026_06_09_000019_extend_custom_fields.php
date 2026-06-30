<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 扩展 custom_field_definitions — 增加 applies_to 和应用范围
        if (!Schema::hasColumn('custom_field_definitions', 'applies_to')) {
            Schema::table('custom_field_definitions', function (Blueprint $table) {
                $table->json('applies_to')->nullable()->after('is_active')
                    ->comment('适用实体: ["license","customer","product"]');
            });
        }

        // 通用自定义字段值表（多态，替代仅 License 的 license_custom_field_values）
        if (!Schema::hasTable('custom_field_values')) {
            Schema::create('custom_field_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('field_definition_id');
                $table->morphs('fieldable'); // fieldable_id + fieldable_type
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['field_definition_id', 'fieldable_id', 'fieldable_type'], 'cfv_unique');
                $table->foreign('field_definition_id', 'cfv_fk_def')
                    ->references('id')->on('custom_field_definitions')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
