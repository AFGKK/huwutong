<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('用户');
            $table->string('type', 20)->default('away')->comment('类型：away/vacation/keyword/busy');
            $table->string('keyword', 100)->nullable()->comment('关键词（type=keyword时）');
            $table->string('match_mode', 20)->default('contains')->comment('匹配方式：exact/contains/regex');
            $table->text('reply_content')->comment('自动回复内容');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->time('time_start')->nullable()->comment('生效起始时间');
            $table->time('time_end')->nullable()->comment('生效结束时间');
            $table->timestamp('expires_at')->nullable()->comment('过期时间（如休假结束）');
            $table->unsignedInteger('reply_count')->default(0)->comment('已回复次数');
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_auto_replies');
    }
};
