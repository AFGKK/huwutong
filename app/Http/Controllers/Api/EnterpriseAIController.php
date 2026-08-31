<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\LlmService;
use App\Services\RagEngineService;
use App\Services\WebSearchService;
use App\Services\AiModeratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * 企业与 Agent AI（§4.15 Phase 4）
 *
 * AI-026 ~ AI-036 共 11 项企业级 AI 功能
 */
class EnterpriseAIController extends Controller
{
    protected RagEngineService $ragService;

    public function __construct(RagEngineService $ragService)
    {
        $this->ragService = $ragService;
    }

    // ════════════════════════════════════════════
    // AI-026: 企业知识库问答 (支持 RAG + 联网搜索)
    // ════════════════════════════════════════════
    public function knowledgeQuery(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:1000',
            'enable_web_search' => 'nullable|boolean',
        ]);
        $q = $request->input('q');
        $enableWeb = $request->boolean('enable_web_search', false);

        // 从 RAG 引擎检索
        $ragResults = $this->ragService->retrieve($q, [
            'min_confidence' => 0.3,
            'max_results' => 5,
        ]);

        $documents = $ragResults['documents'] ?? [];
        $context = '';
        $sources = [];

        foreach ($documents as $doc) {
            $context .= "【{$doc['title']}】\n{$doc['content']}\n\n";
            $sources[] = [
                'title' => $doc['title'],
                'type' => 'knowledge_base',
                'confidence' => $doc['confidence'] ?? 0,
            ];
        }

        // 联网搜索
        $webContext = '';
        $webResults = [];
        if ($enableWeb) {
            $webService = app(WebSearchService::class);
            $searchResult = $webService->search($q);

            if ($searchResult['success'] && !empty($searchResult['results'])) {
                $webContext = $webService->searchAsContext($q);
                foreach ($searchResult['results'] as $i => $item) {
                    $webResults[] = [
                        'title' => $item['title'] ?? '',
                        'url' => $item['url'] ?? '',
                        'type' => 'web',
                        'snippet' => $item['snippet'] ?? $item['content'] ?? '',
                    ];
                }
            } elseif (!$searchResult['success'] && $searchResult['error']) {
                // 搜索不可用时静默处理，不阻塞回答
                $webContext = '';
            }
        }

        try {
            $systemPrompt = '你是一个企业知识库助手。根据提供的知识库内容和联网搜索结果回答员工问题，回复要准确、简洁。';

            if (!empty($webResults)) {
                $systemPrompt .= ' 对于来自联网搜索的信息，请在回答末尾列出引用来源链接（格式：[标题](url)）。';
            }

            $systemPrompt .= ' 如果提供的内容中没有相关信息，请如实告知，不要编造。';

            $messages = [['role' => 'system', 'content' => $systemPrompt]];

            if ($context) {
                $messages[] = ['role' => 'system', 'content' => "参考知识库：\n{$context}"];
            }
            if ($webContext) {
                $messages[] = ['role' => 'system', 'content' => "联网搜索结果：\n{$webContext}"];
            }
            $messages[] = ['role' => 'user', 'content' => $q];

            $result = $llm->chat($messages, ['temperature' => 0.3], 'enterprise_kb_query');
            $reply = $result['content'] ?? __('app.api.ent_ai.not_found');

            // 合并来源
            $allSources = array_merge($sources, $webResults);

            return ApiResponse::success([
                'answer' => $reply,
                'sources' => $allSources,
                'has_knowledge' => !empty($context),
                'web_search_enabled' => $enableWeb,
                'web_results_count' => count($webResults),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'answer' => __('app.api.ent_ai.unavailable'),
                'sources' => [],
                'has_knowledge' => false,
                'web_search_enabled' => $enableWeb,
            ]);
        }
    }

    // ════════════════════════════════════════════
    // AI-027: 会议纪要增强
    // ════════════════════════════════════════════
    public function meetingMinutes(int $convId, LlmService $llm): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'asc')->take(100)->get();
        if ($messages->isEmpty()) return ApiResponse::error(__('app.api.ent_ai.no_messages'), 400);

        $lines = $messages->map(fn($m) => ($m->sender?->name ?? __('app.api.ent_ai.user')).': '.$m->content)->implode("\n");
        $conv = UserConversation::find($convId);

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个会议纪要助手。根据对话生成结构化会议纪要，包含：1）会议主题；2）参与人；3）讨论要点（时间线）；4）决策/结论；5）行动项（负责人+截止时间）。使用 Markdown 格式。'],
                ['role' => 'user', 'content' => "会议名称：{$conv->name}\n对话记录（共{$messages->count()}条消息）：\n{$lines}"],
            ], ['temperature' => 0.3], 'meeting_minutes');

            $minutes = $result['content'] ?? __('app.api.ent_ai.meeting_fail');

            // 提取行动项
            $actionItems = [];
            if (preg_match_all('/[-*]\s*\*\*(.+?)\*\*\s*[:：]?\s*(.+?)(?=\n[-*]|\n\n|$)/s', $minutes, $matches)) {
                foreach ($matches[0] as $match) {
                    $actionItems[] = trim($match);
                }
            }

            return ApiResponse::success([
                'minutes' => $minutes,
                'total_messages' => count($messages),
                'participants' => $messages->pluck('sender.name')->unique()->filter()->values(),
                'action_items' => array_slice($actionItems, 0, 10),
                'conversation_name' => $conv->name,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('MEETING_ERROR', __('app.api.ent_ai.meeting_error'));
        }
    }

    // ════════════════════════════════════════════
    // AI-028: 跨会话洞察
    // ════════════════════════════════════════════
    public function crossSessionInsights(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'max_convs' => 'nullable|integer|min:1|max:50',
        ]);

        $myId = auth()->id();
        $maxConvs = $request->input('max_convs', 10);

        $query = UserConversation::where('user_id', $myId)
            ->whereNotNull('last_message_at');
        if ($request->date_from) $query->where('last_message_at', '>=', $request->date_from);
        if ($request->date_to) $query->where('last_message_at', '<=', $request->date_to);

        $convs = $query->orderBy('last_message_at', 'desc')->take($maxConvs)->get();
        if ($convs->isEmpty()) return ApiResponse::success(['insights' => __('app.api.ent_ai.no_conv_data'), 'summary' => '']);

        $summaries = [];
        foreach ($convs as $conv) {
            $lastMsgs = ConversationMessage::where('conversation_id', $conv->id)
                ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(5)->get()->reverse();
            $snippet = $lastMsgs->map(fn($m) => ($m->sender?->name ?? __('app.api.ent_ai.user')).': '.$m->content)->implode("\n");
            $summaries[] = "【{$conv->name}】\n{$snippet}";
        }

        $allText = implode("\n---\n", $summaries);

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个跨会话洞察助手。分析以下多个会话的内容，输出：1）本周关键话题/决策汇总；2）待跟进的讨论；3）跨会话关联发现。使用简洁的 Markdown 格式。'],
                ['role' => 'user', 'content' => "以下是最近 {$convs->count()} 个会话的摘要：\n{$allText}"],
            ], ['temperature' => 0.3], 'cross_session_insights');

            $insights = $result['content'] ?? __('app.api.ent_ai.insights_fail');

            // 提取关键决策
            $decisions = [];
            if (preg_match_all('/决策[：:]\s*(.+?)(?=\n|$)/u', $insights, $m)) {
                $decisions = $m[1];
            }

            return ApiResponse::success([
                'insights' => $insights,
                'session_count' => $convs->count(),
                'decisions' => array_slice($decisions, 0, 5),
                'time_range' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to,
                ],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'insights' => __('app.api.ent_ai.insights_unavailable', ['count' => $convs->count()]),
                'session_count' => $convs->count(),
            ]);
        }
    }

    // ════════════════════════════════════════════
    // AI-029: 新人 Onboarding Bot
    // ════════════════════════════════════════════
    public function onboardingGuide(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate(['step' => 'nullable|string|max:50']);
        $step = $request->input('step', 'welcome');

        $guide = [
            'welcome' => [
                'title' => __('app.api.ent_ai.welcome_title'),
                'content' => __('app.api.ent_ai.welcome_content'),
                'next_steps' => ['profile', 'features', 'support'],
            ],
            'profile' => [
                'title' => __('app.api.ent_ai.profile_title'),
                'content' => __('app.api.ent_ai.profile_content'),
                'actions' => [__('app.api.ent_ai.profile_action')],
                'next_steps' => ['features', 'support'],
            ],
            'features' => [
                'title' => __('app.api.ent_ai.features_title'),
                'content' => __('app.api.ent_ai.features_content'),
                'next_steps' => ['support', 'done'],
            ],
            'support' => [
                'title' => __('app.api.ent_ai.help_title'),
                'content' => __('app.api.ent_ai.help_content'),
                'next_steps' => ['done'],
            ],
            'done' => [
                'title' => __('app.api.ent_ai.done_title'),
                'content' => __('app.api.ent_ai.done_content'),
                'next_steps' => [],
            ],
        ];

        if (!isset($guide[$step])) $step = 'welcome';
        $current = $guide[$step];

        // 用 LLM 增强个性化
        try {
            $userName = auth()->user()?->name ?? __('app.api.ent_ai.user');
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个新人引导助手。根据当前引导步骤「{$current['title']}」，为用户 {$userName} 生成一段个性化的引导文案，语气友好热情。"],
                ['role' => 'user', 'content' => "当前步骤：{$step}\n基础内容：{$current['content']}"],
            ], ['temperature' => 0.7], 'onboarding_guide');

            $personalized = $result['content'] ?? $current['content'];
        } catch (\Throwable $e) {
            $personalized = $current['content'];
        }

        return ApiResponse::success([
            'step' => $step,
            'title' => $current['title'],
            'content' => $personalized,
            'next_steps' => $current['next_steps'],
            'actions' => $current['actions'] ?? [],
            'progress' => $this->onboardingProgress($step),
        ]);
    }

    // ════════════════════════════════════════════
    // AI-030: 智能填表/审批建议
    // ════════════════════════════════════════════
    public function formSuggestions(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'form_type' => 'required|in:order,refund,contract,ticket,license',
            'form_data' => 'required|array',
        ]);

        $formType = $request->input('form_type');
        $formData = $request->input('form_data');

        $formLabels = [
            'order' => __('app.api.ent_ai.form_order'),
            'refund' => __('app.api.ent_ai.form_refund'),
            'contract' => __('app.api.ent_ai.form_contract'),
            'ticket' => __('app.api.ent_ai.form_ticket'),
            'license' => __('app.api.ent_ai.form_license'),
        ];

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个智能填表助手。检查以下{$formLabels[$formType]}表单数据，指出：1）必填缺失项；2）数据格式问题；3）风险提示/建议。以JSON数组格式返回，每项包含：type(missing/risk/suggestion), field, message。"],

                ['role' => 'user', 'content' => "表单类型：{$formLabels[$formType]}\n表单数据：".json_encode($formData, JSON_UNESCAPED_UNICODE)],
            ], ['temperature' => 0.2], 'form_suggestions');

            $content = $result['content'] ?? '[]';
            $suggestions = [];
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $suggestions = json_decode($matches[0], true) ?: [];
            }

            $hasIssues = !empty($suggestions);
            return ApiResponse::success([
                'suggestions' => $suggestions,
                'has_issues' => $hasIssues,
                'can_submit' => !collect($suggestions)->contains('type', 'missing'),
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'suggestions' => [],
                'has_issues' => false,
                'can_submit' => true,
            ]);
        }
    }

    // ════════════════════════════════════════════
    // AI-031: AI Agent 工具调用
    // ════════════════════════════════════════════
    public function agentToolCall(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'available_tools' => 'nullable|array',
        ]);

        $message = $request->input('message');
        $availableTools = $request->input('available_tools', ['search_order', 'create_ticket', 'check_inventory', 'get_user_info', 'get_license_info']);

        $toolDescriptions = [
            'search_order' => 'search_order(keyword: string) — 搜索订单，参数：关键词',
            'create_ticket' => 'create_ticket(subject: string, description: string, priority: string) — 创建工单',
            'check_inventory' => 'check_inventory(product_id: string) — 查询产品库存',
            'get_user_info' => 'get_user_info(user_id: int) — 获取用户信息',
            'get_license_info' => 'get_license_info(license_key: string) — 查询 License 信息',
        ];

        $toolsStr = '';
        foreach ($availableTools as $t) {
            $desc = isset($toolDescriptions[$t]) ? $toolDescriptions[$t] : $t;
            $toolsStr .= "- {$desc}\n";
        }

        try {
            $promptService = app(\App\Services\PromptTemplateService::class);
            $agentPrompt = $promptService->renderByCategory('agent', [
                'tools_description' => $toolsStr,
                'user_query' => $validated['message'],
                'history' => $history,
            ]);
            $result = $llm->chat([
                ['role' => 'system', 'content' => $agentPrompt ?: "你是一个 AI Agent 助手。理解用户需求，选择合适的工具调用。可用的工具：\n{$toolsStr}\n\n分析用户消息，返回JSON：\n1) 如果需要调用工具：{\"action\":\"call_tool\",\"tool\":\"工具名\",\"params\":{...},\"reason\":\"为什么调用\"}\n2) 如果不需要：{\"action\":\"reply\",\"content\":\"直接回复用户\"}"],

                ['role' => 'user', 'content' => $message],
            ], ['temperature' => 0.2], 'agent_tool_call');

            $content = $result['content'] ?? '{}';
            if (preg_match('/\{.*?\}/s', $content, $matches)) {
                $decision = json_decode($matches[0], true) ?: [];
            } else {
                $decision = ['action' => 'reply', 'content' => __('app.api.ent_ai.cannot_understand')];
            }

            // 模拟工具执行（实际应调用真实服务）
            if (($decision['action'] ?? '') === 'call_tool' && isset($decision['tool'])) {
                $toolResult = $this->executeTool($decision['tool'], $decision['params'] ?? []);
                $decision['result'] = $toolResult;
            }

            return ApiResponse::success($decision);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'action' => 'reply',
                'content' => __('app.api.ent_ai.agent_unavailable'),
            ]);
        }
    }

    // ════════════════════════════════════════════
    // AI-032: No-code Bot 搭建器
    // ════════════════════════════════════════════
    public function botBuilder(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:generate,preview,test',
            'description' => 'required_if:action,generate|string|max:500',
            'config' => 'nullable|array',
        ]);

        $action = $request->input('action');
        $description = $request->input('description', '');

        if ($action === 'generate') {
            try {
                $result = $llm->chat([
                    ['role' => 'system', 'content' => '你是一个 Bot 配置生成助手。根据用户描述，生成一个 AI Bot 的 JSON 配置，包含：name(名称), description(描述), prompt(系统提示词), knowledge_sources(知识库来源列表), tools(可用工具列表), welcome_message(欢迎语), tone(语气: friendly/professional/casual)。只返回 JSON。'],
                    ['role' => 'user', 'content' => $description],
                ], ['temperature' => 0.5], 'bot_builder_generate');

                $content = $result['content'] ?? '{}';
                if (preg_match('/\{.*?\}/s', $content, $matches)) {
                    $config = json_decode($matches[0], true) ?: [];
                } else {
                    $config = ['name' => '自定义 Bot', 'prompt' => $description];
                }

                // 保存到缓存
                $botId = 'bot_'.md5($description.time());
                Cache::put("bot_config:{$botId}", $config, 86400);

                return ApiResponse::success([
                    'bot_id' => $botId,
                    'config' => $config,
                    'preview' => __('app.api.ent_ai.bot_preview', ['name' => $config['name']]),
                ]);
            } catch (\Throwable $e) {
                return ApiResponse::success([
                    'bot_id' => null,
                    'config' => ['name' => 'Bot', 'prompt' => $description],
                    'preview' => __('app.api.ent_ai.bot_basic'),
                ]);
            }
        } elseif ($action === 'preview') {
            $config = $request->input('config', []);
            $name = isset($config['name']) ? $config['name'] : __('app.api.ent_ai.bot_unnamed');
            $desc = isset($config['description']) ? $config['description'] : '';
            $tone = isset($config['tone']) ? $config['tone'] : 'friendly';
            return ApiResponse::success([
                'preview' => __('app.api.ent_ai.bot_preview_full', ['name' => $name, 'desc' => $desc, 'tone' => $tone]),
            ]);
        } else {
            return ApiResponse::success(['test_result' => __('app.api.ent_ai.bot_test_ok')]);
        }
    }

    // ════════════════════════════════════════════
    // AI-033: 多 Agent 协作
    // ════════════════════════════════════════════
    public function multiAgentPipeline(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'task' => 'required|string|max:1000',
            'pipeline' => 'nullable|array',
        ]);

        $task = $request->input('task');
        $pipeline = $request->input('pipeline', ['research', 'write', 'review']);

        $agentRoles = [
            'research' => __('app.api.ent_ai.agent_research'),
            'write' => __('app.api.ent_ai.agent_write'),
            'review' => __('app.api.ent_ai.agent_review'),
            'summarize' => __('app.api.ent_ai.agent_summarize'),
            'translate' => __('app.api.ent_ai.agent_translate'),
        ];

        $results = [];
        $previousOutput = '';
        $pipelineNames = '';
        foreach ($pipeline as $a) {
            $roleName = isset($agentRoles[$a]) ? $agentRoles[$a] : $a;
            $pipelineNames .= ($pipelineNames ? ' → ' : '') . $roleName;
        }

        foreach ($pipeline as $i => $agent) {
            $roleDesc = $agentRoles[$agent] ?? "{$agent} Agent";
            $contextInfo = $i === 0 ? "任务：{$task}" : "上一步输出：\n{$previousOutput}";

            try {
                $result = $llm->chat([
                    ['role' => 'system', 'content' => "你是一个{$roleDesc}。请严格按角色职责完成工作。"],
                    ['role' => 'user', 'content' => $contextInfo],
                ], ['temperature' => 0.5], "multi_agent_{$agent}");

                $output = $result['content'] ?? __('app.api.ent_ai.agent_no_output');
                $results[] = [
                    'agent' => $agent,
                    'role' => $roleDesc,
                    'output' => mb_substr($output, 0, 500),
                    'status' => 'completed',
                ];
                $previousOutput = $output;
            } catch (\Throwable $e) {
                $results[] = [
                    'agent' => $agent,
                    'role' => $roleDesc,
                    'output' => __('app.api.ent_ai.agent_exec_fail'),
                    'status' => 'failed',
                ];
            }
        }

        return ApiResponse::success([
            'pipeline' => $pipelineNames,
            'steps' => $results,
            'final_output' => mb_substr($previousOutput, 0, 2000),
            'total_steps' => count($pipeline),
            'successful' => count(array_filter($results, fn($r) => $r['status'] === 'completed')),
        ]);
    }

    // ════════════════════════════════════════════
    // AI-034: AI 开放 API
    // ════════════════════════════════════════════
    public function openApi(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|in:summarize,translate,analyze_sentiment,extract_tasks,classify_intent',
            'text' => 'required|string|max:5000',
            'options' => 'nullable|array',
        ]);

        $endpoint = $request->input('endpoint');
        $text = $request->input('text');

        $systemPrompts = [
            'summarize' => '你是一个文本摘要助手。为以下内容生成简洁的摘要（不超过200字），包含关键要点。',
            'translate' => '你是一个翻译助手。将以下内容翻译成中文，保持原意和语气。',
            'analyze_sentiment' => '你是一个情感分析助手。分析以下文本的情感倾向，返回JSON：{"sentiment":"positive/negative/neutral","score":0~1,"keywords":["关键词"]}',
            'extract_tasks' => '你是一个任务提取助手。从以下文本中提取待办事项/行动项，返回JSON数组：[{"task":"任务","assignee":"负责人","deadline":"截止时间"}]',
            'classify_intent' => '你是一个意图分类助手。判断以下文本的意图类别，返回JSON：{"intent":"类别","confidence":0~1}',
        ];

        $prompt = $systemPrompts[$endpoint] ?? '请处理以下内容。';

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.3], "open_api_{$endpoint}");

            return ApiResponse::success([
                'endpoint' => $endpoint,
                'result' => $result['content'] ?? '',
                'usage' => $result['usage'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('AI_SERVICE_ERROR', __('app.api.ent_ai.service_unavailable'), 503);
        }
    }

    // ════════════════════════════════════════════
    // AI-036: 企业语料 Fine-tune
    // ════════════════════════════════════════════
    public function finetuneData(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:export,stats,preview',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'conversation_ids' => 'nullable|array',
            'format' => 'nullable|in:openai,jsonl,csv',
        ]);

        $action = $request->input('action');
        $format = $request->input('format', 'openai');

        if ($action === 'stats') {
            $totalConvs = UserConversation::count();
            $totalMsgs = ConversationMessage::count();
            $recentMsgs = ConversationMessage::where('created_at', '>=', now()->subDays(30))->count();
            $uniqueUsers = ConversationMessage::distinct()->pluck('sender_id')->unique()->count();

            return ApiResponse::success([
                'total_conversations' => $totalConvs,
                'total_messages' => $totalMsgs,
                'messages_last_30d' => $recentMsgs,
                'unique_users' => $uniqueUsers,
                'estimated_tokens' => $totalMsgs * 50,
                'recommended_action' => $totalMsgs > 10000 ? __('app.api.ent_ai.data_sufficient') : __('app.api.ent_ai.data_insufficient'),
            ]);
        }

        if ($action === 'preview') {
            $sample = ConversationMessage::whereNull('deleted_at')
                ->where('content', '!=', '')
                ->inRandomOrder()->take(3)->get();

            return ApiResponse::success([
                'samples' => $sample->map(fn($m) => [
                    'conversation_id' => $m->conversation_id,
                    'sender' => $m->sender?->name ?? __('app.api.ent_ai.user'),
                    'content' => mb_substr($m->content, 0, 200),
                    'created_at' => $m->created_at,
                ]),
                'format' => $format,
            ]);
        }

        // export
        $query = ConversationMessage::whereNull('deleted_at')->where('content', '!=', '');
        if ($request->date_from) $query->where('created_at', '>=', $request->date_from);
        if ($request->date_to) $query->where('created_at', '<=', $request->date_to);
        if ($request->conversation_ids) $query->whereIn('conversation_id', $request->conversation_ids);

        $messages = $query->orderBy('conversation_id')->orderBy('created_at')->take(5000)->get();

        // 按会话分组转换为对话对
        $grouped = $messages->groupBy('conversation_id');
        $trainingData = [];

        foreach ($grouped as $convMsgs) {
            $conversation = [];
            foreach ($convMsgs as $msg) {
                $role = $msg->message_type === 'ai_reply' ? 'assistant' : 'user';
                $conversation[] = ['role' => $role, 'content' => $msg->content];
            }
            if (count($conversation) >= 2) {
                if ($format === 'openai') {
                    $trainingData[] = ['messages' => $conversation];
                } else {
                    $trainingData[] = $conversation;
                }
            }
        }

        return ApiResponse::success([
            'total_samples' => count($trainingData),
            'format' => $format,
            'data' => array_slice($trainingData, 0, 100), // 预览前100条
            'sample_count' => min(count($trainingData), 100),
        ]);
    }

    // ════════════════════════════════════════════
    // AI-053: AI 群主持人
    // ════════════════════════════════════════════

    /**
     * 生成讨论议程
     */
    public function moderatorAgenda(int $convId, Request $request, LlmService $llm): JsonResponse
    {
        $this->checkParticipant($convId);
        $topic = $request->input('topic');

        try {
            $moderator = new AiModeratorService($llm);
            $result = $moderator->generateAgenda($convId, $topic);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error('MODERATOR_ERROR', __('app.api.ent_ai.moderator_agenda_fail'), 500);
        }
    }

    /**
     * 争论调解
     */
    public function moderatorMediate(int $convId, LlmService $llm): JsonResponse
    {
        $this->checkParticipant($convId);

        try {
            $moderator = new AiModeratorService($llm);
            $result = $moderator->mediateDebate($convId);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error('MODERATOR_ERROR', __('app.api.ent_ai.moderator_mediation_fail'), 500);
        }
    }

    /**
     * 讨论总结（实时快照）
     */
    public function moderatorSummary(int $convId, LlmService $llm): JsonResponse
    {
        $this->checkParticipant($convId);

        try {
            $moderator = new AiModeratorService($llm);
            $result = $moderator->summarizeDiscussion($convId);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error('MODERATOR_ERROR', __('app.api.ent_ai.moderator_summary_fail'), 500);
        }
    }

    /**
     * 主题专注度检查
     */
    public function moderatorFocus(int $convId, Request $request, LlmService $llm): JsonResponse
    {
        $this->checkParticipant($convId);
        $topic = $request->input('topic', '');
        if (empty($topic)) {
            return ApiResponse::error('VALIDATION', __('app.api.ent_ai.specify_topic'));
        }

        try {
            $moderator = new AiModeratorService($llm);
            $result = $moderator->checkTopicFocus($convId, $topic);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error('MODERATOR_ERROR', __('app.api.ent_ai.moderator_focus_fail'), 500);
        }
    }

    /**
     * 检查当前用户是否为会话参与者
     */
    protected function checkParticipant(int $convId): void
    {
        $participant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', auth()->id())
            ->exists();

        if (!$participant) {
            abort(403, __('app.api.ent_ai.not_participant'));
        }
    }

    // ════════════════════════════════════════════
    // 内部辅助方法
    // ════════════════════════════════════════════

    protected function executeTool(string $tool, array $params): array
    {
        // 模拟工具执行
        $results = [
            'search_order' => ['found' => true, 'orders' => [['id' => 'ORD-2026-001', 'status' => 'active', 'amount' => 2999]]],
            'create_ticket' => ['created' => true, 'ticket_id' => 'TKT-' . rand(10000, 99999)],
            'check_inventory' => ['product' => $params['product_id'] ?? 'unknown', 'available' => rand(0, 100)],
            'get_user_info' => ['user_id' => $params['user_id'] ?? 0, 'name' => '测试用户', 'role' => 'admin'],
            'get_license_info' => ['license_key' => $params['license_key'] ?? '', 'status' => 'active', 'expires_at' => '2027-06-18'],
        ];

        return $results[$tool] ?? ['error' => __('app.api.ent_ai.unknown_tool')];
    }

    protected function onboardingProgress(string $step): array
    {
        $steps = ['welcome', 'profile', 'features', 'support', 'done'];
        $current = array_search($step, $steps);
        $total = count($steps) - 1;
        return [
            'current' => max(0, $current),
            'total' => $total,
            'percent' => $total > 0 ? round((max(0, $current) / $total) * 100) : 100,
        ];
    }
}
