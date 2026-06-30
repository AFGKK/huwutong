<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sdk_versions')) {
            return;
        }
        Schema::create('sdk_versions', function (Blueprint $table) {
            $table->id();
            $table->string('language', 20)->comment('php/node/python/go/java');
            $table->string('version', 20)->comment('语义化版本号');
            $table->string('stage', 20)->default('stable')->comment('preview/stable/deprecated/sunset');
            $table->boolean('is_current')->default(false)->comment('是否为当前推荐版本');
            $table->boolean('allow_production')->default(true);
            $table->string('min_api_version', 10)->default('v1');
            $table->text('changelog')->nullable();
            $table->text('upgrade_notes')->nullable()->comment('升级说明');
            $table->string('compatible_sdk_versions', 200)->nullable()->comment('兼容的SDK版本范围');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamp('sunset_at')->nullable();
            $table->timestamps();

            $table->unique(['language', 'version']);
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdk_versions');
    }
};
