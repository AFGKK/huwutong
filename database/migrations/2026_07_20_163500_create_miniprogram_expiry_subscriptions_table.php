<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A4: 微信小程序 License 过期订阅消息
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miniprogram_expiry_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wechat_openid', 64)->index();
            $table->string('license_key', 100)->index();
            $table->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->dateTime('license_expires_at')->nullable();
            $table->unsignedTinyInteger('remind_days')->default(7);
            $table->string('status', 20)->default('active')->index(); // active|sent|cancelled
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'license_key'], 'mp_expiry_user_license_unique');
        });

        if (Schema::hasTable('site_settings')) {
            $exists = \Illuminate\Support\Facades\DB::table('site_settings')
                ->where('key', 'wechat_mini_subscribe_template_id')
                ->exists();
            if (! $exists) {
                \Illuminate\Support\Facades\DB::table('site_settings')->insert([
                    'group' => 'wechat',
                    'key' => 'wechat_mini_subscribe_template_id',
                    'value' => '',
                    'type' => 'text',
                    'description' => '小程序过期提醒订阅消息模板ID',
                    'is_public' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('miniprogram_expiry_subscriptions');
    }
};
