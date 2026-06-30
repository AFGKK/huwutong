<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token 黑名单（实时吊销缓存，持久化存储）
        Schema::create('token_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->index()->comment('personal_access_tokens.id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('reason', 50)->default('revoked')->comment('revoked/password_changed/logout_all/admin');
            $table->timestamp('revoked_at')->useCurrent();
            $table->timestamps();

            $table->index('revoked_at');
        });

        // 用户 Token 版本号（密码修改/权限变更时自增，早期失效所有老 Token）
        Schema::create('user_token_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_bumped_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 为 personal_access_tokens 添加额外字段（如果尚不存在）
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'token_version')) {
                $table->unsignedInteger('token_version')->default(1)->after('abilities')->comment('创建时的用户 token 版本');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('token_version');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_blacklist');
        Schema::dropIfExists('user_token_versions');
    }
};
