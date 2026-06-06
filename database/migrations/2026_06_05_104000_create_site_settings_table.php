<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general')->comment('分组: general/brand/seo/contact/social');
            $table->string('key')->unique()->comment('设置键名');
            $table->text('value')->nullable()->comment('设置值');
            $table->string('type')->default('text')->comment('值类型: text/textarea/image/color/switch/select');
            $table->json('options')->nullable()->comment('select 类型的可选值');
            $table->text('description')->nullable()->comment('说明');
            $table->boolean('is_public')->default(false)->comment('是否公开（前端可读）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
