<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kb_auto_grow_drafts')) {
            Schema::create('kb_auto_grow_drafts', function (Blueprint $table) {
                $table->id();
                $table->string('title', 300);
                $table->longText('content');
                $table->string('excerpt', 500)->nullable();
                $table->json('tags')->nullable();
                $table->string('source_type', 50)->comment('rag_chat/handoff/forum_post/im_chat');
                $table->unsignedBigInteger('source_id')->nullable()->comment('来源记录ID');
                $table->string('source_summary', 200)->nullable()->comment('来源描述');
                $table->decimal('confidence', 5, 2)->default(0.0)->comment('AI 置信度 0~1');
                $table->string('status', 20)->default('pending')->comment('pending/approved/rejected');
                $table->unsignedBigInteger('kb_article_id')->nullable()->comment('审核通过后关联的 KB 文章ID');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('source_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_auto_grow_drafts');
    }
};
