<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_feedback')) {
            return;
        }
        Schema::create('customer_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->default('general')->comment('bug/feature_request/general/improvement');
            $table->string('title', 300)->nullable();
            $table->text('description');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('open')->comment('open/in_review/planned/in_progress/resolved/closed');
            $table->string('category', 100)->nullable()->comment('自动分类');
            $table->json('screenshots')->nullable();
            $table->json('context')->nullable()->comment('浏览器/OS/URL/版本等上下文');
            $table->json('metadata')->nullable();
            $table->string('source', 30)->default('widget')->comment('widget/api/email/manual');
            $table->string('assignee', 100)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['type', 'priority']);
        });

        Schema::create('customer_feedback_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_feedback_id')->constrained('customer_feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedback_comments');
        Schema::dropIfExists('customer_feedback');
    }
};
