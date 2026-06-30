<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_moderations')) {
            Schema::create('content_moderations', function (Blueprint $table) {
                $table->id();
                $table->morphs('moderatable');
                $table->decimal('quality_score', 4, 2)->default(0)->comment('质量评分 0~1');
                $table->string('moderation_status', 20)->default('approved')->comment('approved/flagged/folded/archived/deleted');
                $table->string('reason', 100)->nullable()->comment('自动处理原因');
                $table->string('action_taken', 50)->nullable()->comment('fold/archive/warn/delete');
                $table->json('details')->nullable()->comment('评分详情');
                $table->unsignedBigInteger('moderated_by')->nullable()->comment('AI 或管理员ID');
                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();

                $table->index('moderation_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_moderations');
    }
};
