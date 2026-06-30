<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('electronic_signatures')) {
            Schema::create('electronic_signatures', function (Blueprint $table) {
                $table->id();
                $table->string('signable_type', 100)->comment('签名对象类型：合同/消息/审批单');
                $table->unsignedBigInteger('signable_id');
                $table->unsignedBigInteger('user_id')->comment('签署人');
                $table->string('signature_hash', 64)->comment('签名内容 SHA-256');
                $table->text('signature_data')->nullable()->comment('签名数据（加密）');
                $table->string('status', 20)->default('pending')->comment('pending/signed/rejected/expired');
                $table->string('type', 30)->default('single')->comment('single/multi/approval');
                $table->integer('sequence')->default(1)->comment('签署顺序');
                $table->string('ip_address', 45)->nullable();
                $table->text('remark')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['signable_type', 'signable_id', 'user_id']);
                $table->index(['signable_type', 'signable_id', 'status']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_signatures');
    }
};
