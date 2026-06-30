<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_signatures')) {
            Schema::create('content_signatures', function (Blueprint $table) {
                $table->id();
                $table->string('source_type', 50)->comment('ai_reply/kb_article/rag_answer');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('content_hash', 64)->comment('SHA-256 内容哈希');
                $table->text('signature')->comment('HMAC-SHA256 数字签名');
                $table->string('signing_key_id', 32)->comment('签名密钥标识');
                $table->string('content_preview', 200)->nullable()->comment('内容预览');
                $table->json('metadata')->nullable()->comment('额外元数据');
                $table->timestamp('signed_at')->useCurrent();
                $table->timestamps();

                $table->unique('content_hash');
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_signatures');
    }
};
