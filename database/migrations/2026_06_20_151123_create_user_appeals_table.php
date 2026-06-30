<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('申诉用户');
            $table->string('status', 20)->default('pending')->comment('pending/reviewing/approved/rejected');
            $table->text('reason')->comment('申诉理由');
            $table->text('explanation')->nullable()->comment('详细说明');
            $table->json('attachments')->nullable()->comment('证明材料（JSON 数组 URL）');
            $table->string('contact_email')->nullable()->comment('联系邮箱');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->timestamp('reviewed_at')->nullable()->comment('审核时间');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->comment('审核人');
            $table->text('review_comment')->nullable()->comment('审核意见');
            $table->timestamp('appealed_at')->nullable()->comment('申诉时间');
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_appeals');
    }
};
