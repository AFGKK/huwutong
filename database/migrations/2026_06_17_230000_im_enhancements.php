<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 快捷回复（协作模块已创建 canned_replies，此处仅补 IM 扩展字段）
        if (! Schema::hasTable('canned_replies')) {
            Schema::create('canned_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('category', 50)->nullable()->comment('分类: common/billing/tech/sale');
                $table->string('title', 100)->comment('标题');
                $table->text('content')->comment('回复内容');
                $table->json('shortcuts')->nullable()->comment('快捷触发词');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_shared')->default(false)->comment('是否全员共享');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('canned_replies', function (Blueprint $table) {
                if (! Schema::hasColumn('canned_replies', 'shortcuts')) {
                    $table->json('shortcuts')->nullable()->comment('快捷触发词');
                }
                if (! Schema::hasColumn('canned_replies', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        // 2. 会话标签
        if (! Schema::hasTable('conversation_tags')) {
            Schema::create('conversation_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('color', 20)->default('#409eff')->comment('标签颜色');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        // 会话-标签关联
        if (! Schema::hasTable('conversation_tag_assignments')) {
            Schema::create('conversation_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_type', 50)->comment('live_chat/handoff');
            $table->unsignedBigInteger('conversation_id');
            $table->foreignId('tag_id')->constrained('conversation_tags')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conversation_type', 'conversation_id', 'tag_id'], 'conv_tag_unique');
            });
        }

        // 3. 客服组/部门
        if (! Schema::hasTable('agent_groups')) {
            Schema::create('agent_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->string('color', 20)->default('#409eff');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        // 客服组成员
        if (! Schema::hasTable('agent_group_members')) {
            Schema::create('agent_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('agent_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member')->comment('leader/member');
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            });
        }

        // 4. 自动回复规则
        if (! Schema::hasTable('auto_reply_rules')) {
            Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('规则名称');
            $table->string('trigger_type', 30)->default('keyword')->comment('keyword/regex/all');
            $table->text('trigger_value')->nullable()->comment('触发关键词（逗号分隔）或正则');
            $table->enum('match_mode', ['exact', 'contains', 'regex'])->default('contains');
            $table->text('reply_content')->comment('回复内容');
            $table->foreignId('agent_group_id')->nullable()->constrained('agent_groups')->nullOnDelete()->comment('匹配后转指定组');
            $table->integer('priority')->default(0)->comment('优先级，高者优先');
            $table->integer('match_count')->default(0)->comment('匹配次数统计');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        // 给 live_chat_conversations 添加字段
        if (Schema::hasTable('live_chat_conversations')) {
            Schema::table('live_chat_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('live_chat_conversations', 'department')) {
                $table->string('department', 50)->nullable()->after('status')->comment('来源部门');
            }
            if (!Schema::hasColumn('live_chat_conversations', 'agent_group_id')) {
                $table->foreignId('agent_group_id')->nullable()->constrained('agent_groups')->nullOnDelete()->after('department');
            }
            if (!Schema::hasColumn('live_chat_conversations', 'assigned_agent_id')) {
                $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete()->after('agent_group_id');
            }
            });
        }

        // 给 live_chat_messages 添加附件字段
        if (Schema::hasTable('live_chat_messages')) {
            Schema::table('live_chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('live_chat_messages', 'message_type')) {
                $table->string('message_type', 30)->default('text')->after('content')->comment('text/image/file');
            }
            if (!Schema::hasColumn('live_chat_messages', 'attachments')) {
                $table->json('attachments')->nullable()->after('message_type');
            }
            });
        }

        // 客服绩效日志表
        if (! Schema::hasTable('agent_performance_logs')) {
            Schema::create('agent_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('conversations_count')->default(0);
            $table->integer('messages_count')->default(0);
            $table->integer('avg_response_seconds')->default(0);
            $table->decimal('satisfaction_score', 3, 2)->nullable();
            $table->integer('handoffs_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'log_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_performance_logs');
        Schema::dropIfExists('auto_reply_rules');
        Schema::dropIfExists('agent_group_members');
        Schema::dropIfExists('agent_groups');
        Schema::dropIfExists('conversation_tag_assignments');
        Schema::dropIfExists('conversation_tags');

        if (Schema::hasTable('canned_replies')) {
            Schema::table('canned_replies', function (Blueprint $table) {
                if (Schema::hasColumn('canned_replies', 'shortcuts')) {
                    $table->dropColumn('shortcuts');
                }
                if (Schema::hasColumn('canned_replies', 'is_active')) {
                    $table->dropColumn('is_active');
                }
            });
        }

        if (Schema::hasTable('live_chat_conversations')) {
            Schema::table('live_chat_conversations', function (Blueprint $table) {
                $columns = array_filter(
                    ['department', 'agent_group_id', 'assigned_agent_id'],
                    fn (string $column) => Schema::hasColumn('live_chat_conversations', $column),
                );
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('live_chat_messages')) {
            Schema::table('live_chat_messages', function (Blueprint $table) {
                $columns = array_filter(
                    ['message_type', 'attachments'],
                    fn (string $column) => Schema::hasColumn('live_chat_messages', $column),
                );
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
