<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 市场推送活动
        if (!Schema::hasTable('marketplace_push_campaigns')) {
            Schema::create('marketplace_push_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->string('type')->default('marketing'); // marketing / update / promo / info
                $table->string('target_type')->default('all'); // all / installed_app / category / specific_app
                $table->unsignedBigInteger('target_app_id')->nullable();
                $table->string('target_category')->nullable();
                $table->string('link_type')->nullable(); // app / url
                $table->string('link_value')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status')->default('draft'); // draft / scheduled / sending / sent / cancelled
                $table->integer('target_count')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('read_count')->default(0);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'scheduled_at']);
                $table->index('type');
            });
        }

        // 推送投递记录
        if (!Schema::hasTable('marketplace_push_deliveries')) {
            Schema::create('marketplace_push_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('marketplace_push_campaigns')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel')->default('in_app'); // in_app / email
                $table->string('status')->default('pending'); // pending / sent / delivered / read / failed
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'user_id']);
                $table->index(['campaign_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_push_deliveries');
        Schema::dropIfExists('marketplace_push_campaigns');
    }
};
