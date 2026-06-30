<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('模板名称');
            $table->string('category', 50)->comment('分类');
            $table->text('content')->comment('Prompt 模板内容');
            $table->text('description')->nullable()->comment('模板说明');
            $table->json('variables')->nullable()->comment('变量列表');
            $table->string('version', 10)->default('1.0')->comment('版本号');
            $table->string('status', 20)->default('active')->comment('active/draft/archived');
            $table->boolean('is_current')->default(true)->comment('当前生效版本');
            $table->string('engine', 30)->default('deepseek')->comment('推荐模型');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(2000);
            $table->json('ab_test_config')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'version']);
            $table->index('category');
            $table->index('status');
        });

        $now = now();
        $rows = [];
        $data = [
            ['客服对话 System Prompt','chat',"你是一个专业的软件授权客服助手，名称是「互物通智能助手」。\n\n能力:\n- 回答 License 激活/验证/续费/设备管理等问题\n- 基于知识库提供准确答案\n- 超出范围时引导转人工\n\n准则:\n- 友好专业，中文回答，不超过200字\n- 不确定时说「我需要核实一下」\n- 情绪激动时安抚并建议转人工\n\n上下文:\n主题: {topic}\n意图: {intent_history}\n知识库: {rag_context}",'AI 客服默认 System Prompt','["topic","intent_history","rag_context"]','1.0','active',1,'deepseek',0.7,2000],
            ['会话摘要 Prompt','summary',"请对以下对话进行简洁摘要。\n\n{messages}\n\n要求:\n1. 3-5句概括核心内容\n2. 提取决策和待办\n3. 标注涉及的客户/产品/License",'聊天内容摘要','["messages"]','1.0','active',1,'deepseek',0.3,1500],
            ['情感分析 Prompt','sentiment',"分析以下消息的情感。\n\n消息: {message}\n\nJSON输出：sentiment(positive/neutral/negative/angry/anxious)、score(0-1)、urgency(low/medium/high)、need_human(bool)",'情感分析','["message"]','1.0','active',1,'deepseek',0.1,500],
            ['翻译 Prompt','translation',"将以下文本从 {source_lang} 翻译到 {target_lang}。\n\n{text}\n\n只返回翻译结果。",'多语言翻译','["source_lang","target_lang","text"]','1.0','active',1,'deepseek',0.2,2000],
            ['会话质检 Prompt','quality',"质检客服对话。\n\n客服: {agent_messages}\n客户: {customer_messages}\n\n评分1-5：礼貌度/解决率/违规用语/响应速度\nJSON输出评分和改进建议。",'会话质量检查','["agent_messages","customer_messages"]','1.0','active',1,'deepseek',0.2,2000],
            ['待办提取 Prompt','todo',"从对话提取待办。\n\n{messages}\n\nJSON数组：[{\"title\":\"\",\"assignee\":\"\",\"deadline\":\"\",\"priority\":\"high|medium|low\"}]",'待办提取','["messages"]','1.0','active',1,'deepseek',0.2,1500],
            ['Agent 工具调用 Prompt','agent',"你是一个智能助手，根据需求调用工具。\n\n工具:\n{tools_description}\n\n请求: {user_query}\n\nJSON输出意图和工具调用。",'Agent Function Calling','["tools_description","user_query","history"]','1.0','active',1,'deepseek',0.3,2000],
        ];
        foreach ($data as $d) {
            $rows[] = [
                'name' => $d[0], 'category' => $d[1], 'content' => $d[2],
                'description' => $d[3], 'variables' => $d[4], 'version' => $d[5],
                'status' => $d[6], 'is_current' => $d[7], 'engine' => $d[8],
                'temperature' => $d[9], 'max_tokens' => $d[10],
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('prompt_templates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
