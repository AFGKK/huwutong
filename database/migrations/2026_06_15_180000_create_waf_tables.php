<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WAF 规则表（动态规则，可后台管理）
        Schema::create('waf_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('规则名称');
            $table->string('category')->index()->comment('规则分类: sqli|xss|path_traversal|cmd_injection|file_inclusion|ssrf|custom');
            $table->string('severity', 20)->default('high')->comment('严重级别: low|medium|high|critical');
            $table->string('mode', 20)->default('block')->comment('模式: block|detect|simulate');
            $table->string('match_type', 30)->default('regex')->comment('匹配类型: regex|exact|prefix|suffix|contains');
            $table->text('pattern')->comment('匹配模式（正则表达式或精确字符串）');
            $table->string('target', 50)->default('all')->comment('检测目标: all|query|body|headers|cookies|uri');
            $table->string('action', 30)->default('block')->comment('动作: block|challenge|log|allow');
            $table->text('description')->nullable()->comment('规则描述');
            $table->text('recommendation')->nullable()->comment('修复建议');
            $table->json('scope')->nullable()->comment('作用域: 路径白名单/方法白名单');
            $table->boolean('is_active')->default(true)->index();
            $table->integer('priority')->default(100)->comment('优先级（值越小越优先）');
            $table->integer('hit_count')->default(0)->comment('命中次数');
            $table->timestamp('last_hit_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });

        // IP 黑白名单表
        Schema::create('waf_ip_lists', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->comment('IP 地址或 CIDR');
            $table->string('type', 20)->default('blacklist')->comment('blacklist|whitelist|challenge');
            $table->string('source', 30)->default('manual')->comment('来源: manual|auto|cloudflare|synced');
            $table->text('reason')->nullable()->comment('原因');
            $table->integer('hit_count')->default(0);
            $table->timestamp('expires_at')->nullable()->comment('过期时间（null=永久）');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index(['ip', 'type']);
        });

        // 攻击事件日志表
        Schema::create('waf_attack_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique()->comment('事件唯一标识');
            $table->string('ip', 45)->index()->comment('来源 IP');
            $table->string('country', 10)->nullable()->comment('国家代码');
            $table->string('method', 10)->nullable();
            $table->string('uri', 500)->nullable();
            $table->string('rule_category', 50)->index()->comment('规则分类');
            $table->string('rule_name', 200)->nullable()->comment('命中的规则名称');
            $table->string('matched_pattern', 200)->nullable()->comment('匹配的模式');
            $table->text('matched_value')->nullable()->comment('匹配的原始值');
            $table->string('target', 50)->nullable()->comment('检测目标');
            $table->string('severity', 20)->default('high');
            $table->string('action_taken', 30)->default('block')->comment('执行动作');
            $table->string('user_agent', 500)->nullable();
            $table->json('headers')->nullable()->comment('请求头快照');
            $table->text('request_body')->nullable()->comment('请求体（截断）');
            $table->unsignedBigInteger('user_id')->nullable()->comment('认证用户ID');
            $table->string('session_id', 100)->nullable();
            $table->boolean('is_whitelisted')->default(false)->comment('是否在白名单中');
            $table->boolean('is_trusted_ip')->default(false)->comment('是否受信任 IP');
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['rule_category', 'created_at']);
            $table->index(['severity', 'created_at']);
        });

        // CC 防护频率限制表
        Schema::create('waf_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->index()->comment('限流指纹（IP+UA 哈希）');
            $table->string('ip', 45)->index();
            $table->string('action', 30)->default('block')->comment('当前动作');
            $table->integer('request_count')->default(1);
            $table->integer('window_start')->comment('窗口开始时间戳');
            $table->integer('window_end')->comment('窗口结束时间戳');
            $table->integer('blocked_until')->nullable()->comment('封禁到期时间戳');
            $table->integer('challenge_failures')->default(0)->comment('挑战失败次数');
            $table->boolean('is_challenged')->default(false);
            $table->string('path_spread', 500)->nullable()->comment('访问路径分布');
            $table->timestamps();

            $table->index(['fingerprint', 'window_end']);
            $table->index(['ip', 'blocked_until']);
        });

        // WAF 统计汇总表（定时聚合）
        Schema::create('waf_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('hour', 4)->index()->comment('小时: 00-23');
            $table->string('rule_category', 50)->index();
            $table->integer('total_hits')->default(0);
            $table->integer('blocked_count')->default(0);
            $table->integer('challenged_count')->default(0);
            $table->integer('detected_only')->default(0);
            $table->string('top_ip', 45)->nullable()->comment('攻击最多的IP');
            $table->string('top_uri', 500)->nullable()->comment('攻击最多的URI');
            $table->timestamps();

            $table->unique(['date', 'hour', 'rule_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waf_stats');
        Schema::dropIfExists('waf_rate_limits');
        Schema::dropIfExists('waf_attack_logs');
        Schema::dropIfExists('waf_ip_lists');
        Schema::dropIfExists('waf_rules');
    }
};
