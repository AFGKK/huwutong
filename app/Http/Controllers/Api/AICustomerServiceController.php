<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;
use App\Models\HandoffRequest;
use App\Models\User;
use App\Services\LlmService;
use App\Services\WebSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 客服 AI 包（§4.14 Phase 4）
 *
 * AI-019 ~ AI-025 共 7 项客服智能功能
 */
class AICustomerServiceController extends Controller
{
    const CACHE_TTL = 3600; // 1小时

    // ── AI-019: AI 自动客服（RAG）──
    // FAQ + 文档自动回答，附引用来源
    public function autoReply(Request $request, LlmService $llm): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:user_conversations,id',
            'enable_web_search' => 'nullable|boolean',
        ]);
        $message = $validated['message'];
        $convId = $validated['conversation_id'] ?? null;
        $enableWeb = $request->boolean('enable_web_search', false);

        // 从 FAQ 知识库检索
        $faqEntries = $this->getFaqEntries();
        $matchedFaq = $this->matchFaq($message, $faqEntries);

        // 历史相似问题检索
        $similarCases = [];
        if ($convId) {
            $similarCases = $this->findSimilarCases($message, $convId);
        }

        // 联网搜索
        $webContext = '';
        $webSources = [];
        if ($enableWeb) {
            $webService = app(WebSearchService::class);
            $searchResult = $webService->search($message);
            if ($searchResult['success'] && !empty($searchResult['results'])) {
                $webContext = $webService->searchAsContext($message);
                foreach ($searchResult['results'] as $item) {
                    $webSources[] = [
                        'title' => $item['title'] ?? '',
                        'url' => $item['url'] ?? '',
                    ];
                }
            }
        }

        try {
            $contextInfo = '';
            if ($matchedFaq) {
                $contextInfo = "相关 FAQ：\nQ: {$matchedFaq['question']}\nA: {$matchedFaq['answer']}\n\n";
            }
            if (!empty($similarCases)) {
                $contextInfo .= "历史类似对话：\n";
                foreach (array_slice($similarCases, 0, 2) as $case) {
                    $contextInfo .= "- {$case['question']} → {$case['answer']}\n";
                }
            }
            if ($webContext) {
                $contextInfo .= "\n联网搜索结果：\n{$webContext}";
            }

            $promptService = app(\App\Services\PromptTemplateService::class);
            $systemPrompt = $promptService->renderByCategory('chat', [
                'topic' => '客服FAQ',
                'intent_history' => '',
                'rag_context' => $contextInfo ?: __('app.api.ai_cs.none'),
            ]);
            if (empty($systemPrompt)) {
                $systemPrompt = '你是一个智能客服助手。请根据 FAQ 知识库和上下文回答用户问题，回复要简洁、准确、友好。如果问题超出知识库范围，请礼貌告知并引导用户联系人工客服。回复末尾附上引用来源（如有）。';
            }

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            if ($contextInfo) {
                $messages[] = ['role' => 'system', 'content' => "参考信息：\n{$contextInfo}"];
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            $result = $llm->chat($messages, ['temperature' => 0.3], 'cs_auto_reply');
            $reply = $result['content'] ?? __('app.api.ai_cs.fallback_reply');

            // 置信度评估
            $confidence = $this->evaluateConfidence($reply, $message, $matchedFaq, $llm);

            $needsReview = $confidence < 0.6;
            if ($needsReview) {
                $reply .= __('app.api.ai_cs.low_confidence_suffix');
            }

            return ApiResponse::success([
                'reply' => $reply,
                'confidence' => round($confidence, 2),
                'needs_review' => $needsReview,
                'matched_faq' => $matchedFaq ? [
                    'question' => $matchedFaq['question'],
                    'answer' => $matchedFaq['answer'],
                ] : null,
                'sources' => $matchedFaq ? [$matchedFaq['question']] : [],
                'web_sources' => $webSources ?: [],
                'web_search_enabled' => $enableWeb,
            ]);
        } catch (\Throwable $e) {
            Log::error('[AI-019] autoReply error: '.$e->getMessage());
            return ApiResponse::success([
                'reply' => __('app.api.ai_cs.unavailable'),
                'confidence' => 0,
                'needs_review' => true,
                'sources' => [],
            ]);
        }
    }

    // ── AI-019s: AI 自动客服（流式 SSE）──
    public function autoReplyStream(Request $request, LlmService $llm): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:user_conversations,id',
            'enable_web_search' => 'nullable|boolean',
        ]);
        $message = $validated['message'];
        $convId = $validated['conversation_id'] ?? null;
        $enableWeb = $request->boolean('enable_web_search', false);

        // 预检索上下文（同 autoReply）
        $faqEntries = $this->getFaqEntries();
        $matchedFaq = $this->matchFaq($message, $faqEntries);
        $similarCases = $convId ? $this->findSimilarCases($message, $convId) : [];

        $webContext = '';
        if ($enableWeb) {
            try {
                $webService = app(WebSearchService::class);
                $webContext = $webService->searchAsContext($message);
            } catch (\Throwable $e) {
                Log::warning('[AI-019s] web search failed: '.$e->getMessage());
            }
        }

        $contextInfo = '';
        if ($matchedFaq) {
            $contextInfo .= "相关 FAQ：\nQ: {$matchedFaq['question']}\nA: {$matchedFaq['answer']}\n\n";
        }
        if (!empty($similarCases)) {
            $contextInfo .= "历史类似对话：\n";
            foreach (array_slice($similarCases, 0, 2) as $case) {
                $contextInfo .= "- {$case['question']} → {$case['answer']}\n";
            }
        }
        if ($webContext) {
            $contextInfo .= "\n联网搜索结果：\n{$webContext}";
        }

        $promptService = app(\App\Services\PromptTemplateService::class);
        $systemPrompt = $promptService->renderByCategory('chat', [
            'topic' => '客服FAQ',
            'intent_history' => '',
            'rag_context' => $contextInfo ?: __('app.api.ai_cs.none'),
        ]);
        if (empty($systemPrompt)) {
            $systemPrompt = '你是一个智能客服助手。请根据 FAQ 知识库和上下文回答用户问题，回复要简洁、准确、友好。';
        }

        $msgs = [['role' => 'system', 'content' => $systemPrompt]];
        if ($contextInfo) {
            $msgs[] = ['role' => 'system', 'content' => "参考信息：\n{$contextInfo}"];
        }
        $msgs[] = ['role' => 'user', 'content' => $message];

        $response = new StreamedResponse(function () use ($llm, $msgs, $matchedFaq) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            // 发送元数据
            $meta = [
                'matched_faq' => $matchedFaq ? ['question' => $matchedFaq['question'], 'answer' => $matchedFaq['answer']] : null,
            ];
            echo "data: " . json_encode(['type' => 'meta', 'data' => $meta]) . "\n\n";
            ob_flush(); flush();

            $fullContent = '';
            foreach ($llm->chatStream($msgs, ['temperature' => 0.3]) as $chunk) {
                $fullContent .= $chunk;
                echo "data: " . json_encode(['type' => 'chunk', 'data' => $chunk]) . "\n\n";
                ob_flush(); flush();
            }

            echo "data: " . json_encode(['type' => 'done', 'data' => ['full_content' => $fullContent]]) . "\n\n";
            ob_flush(); flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    // ── AI-020: 置信度评估 ──
    public function evaluateConfidence(string $reply, string $originalMsg, ?array $matchedFaq, LlmService $llm): float
    {
        // 如果有精确 FAQ 匹配，置信度较高
        if ($matchedFaq && $this->isExactMatch($originalMsg, $matchedFaq)) {
            return 0.95;
        }

        // 关键词覆盖度评估
        $keywords = $this->extractKeywords($originalMsg);
        $matchedKeywords = 0;
        foreach ($keywords as $kw) {
            if (mb_strpos($reply, $kw) !== false) {
                $matchedKeywords++;
            }
        }
        $keywordScore = count($keywords) > 0 ? $matchedKeywords / count($keywords) : 0.5;

        // 回复长度合理性
        $lengthScore = min(mb_strlen($reply) / 200, 1.0);

        // LLM 自评
        $llmScore = 0.5;
        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个回复质量评估助手。评估以下客服回复的置信度（0~1），考虑：是否直接回答了问题、是否有依据、是否含糊。只返回一个0~1之间的数字。'],
                ['role' => 'user', 'content' => "用户问题：{$originalMsg}\n\nAI 回复：{$reply}"],
            ], ['temperature' => 0.1, 'max_tokens' => 10], 'cs_confidence');
            $llmScore = max(0, min(1, (float) ($result['content'] ?? 0.5)));
        } catch (\Throwable $e) {
            // 降级
        }

        // 加权综合
        return 0.3 * $keywordScore + 0.2 * $lengthScore + 0.5 * $llmScore;
    }

    // ── AI-021: 意图识别 + 路由 ──
    public function intentClassification(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $message = $request->input('message');

        $intents = [
            'inquiry' => __('app.api.ai_cs.intent_inquiry'),
            'complaint' => __('app.api.ai_cs.intent_complaint'),
            'after_sale' => __('app.api.ai_cs.intent_after_sale'),
            'purchase' => __('app.api.ai_cs.intent_purchase'),
            'technical' => __('app.api.ai_cs.intent_technical'),
            'billing' => __('app.api.ai_cs.intent_billing'),
            'account' => __('app.api.ai_cs.intent_account'),
            'other' => __('app.api.ai_cs.intent_other'),
        ];

        $intentDescriptions = implode("\n", array_map(fn($k, $v) => "- {$k}: {$v}", array_keys($intents), $intents));

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个意图识别助手。判断用户消息属于以下哪种意图，只返回意图代码：\n{$intentDescriptions}"],
                ['role' => 'user', 'content' => $message],
            ], ['temperature' => 0.1, 'max_tokens' => 20], 'cs_intent');

            $intent = trim($result['content'] ?? 'other');
            if (!isset($intents[$intent])) $intent = 'other';
        } catch (\Throwable $e) {
            // 降级：关键词匹配
            $intent = $this->fallbackIntent($message);
        }

        // 路由到技能组
        $skillGroup = $this->routeToGroup($intent);

        return ApiResponse::success([
            'intent' => $intent,
            'intent_label' => $intents[$intent] ?? __('app.api.ai_cs.intent_other'),
            'skill_group' => $skillGroup,
            'priority' => in_array($intent, ['complaint', 'after_sale']) ? 'high' : 'normal',
        ]);
    }

    // ── AI-022: 情感分析 ──
    public function sentimentAnalysis(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $message = $request->input('message');

        // 快速规则检测
        $negativeWords = ['愤怒', '生气', '差评', '投诉', '垃圾', '恶心', '骗子', '退款', '赔偿', '投诉', '垃圾', '烂'];
        $positiveWords = ['谢谢', '感谢', '很棒', '好用', '满意', '赞', '不错', '喜欢', '好评'];

        $hasNegative = preg_match('/'.implode('|', $negativeWords).'/i', $message);
        $hasPositive = preg_match('/'.implode('|', $positiveWords).'/i', $message);

        $ruleSentiment = 'neutral';
        $ruleScore = 0.5;
        if ($hasNegative && !$hasPositive) {
            $ruleSentiment = 'negative';
            $ruleScore = 0.3;
        } elseif ($hasPositive && !$hasNegative) {
            $ruleSentiment = 'positive';
            $ruleScore = 0.8;
        }

        // LLM 增强
        try {
            $promptService = app(\App\Services\PromptTemplateService::class);
            $sentimentPrompt = $promptService->renderByCategory('sentiment', ['message' => $message]);
            $result = $llm->chat([
                ['role' => 'system', 'content' => $sentimentPrompt ?: '你是一个情感分析助手。分析以下消息的情感倾向：positive(积极)、negative(消极)、neutral(中性)、angry(愤怒)、anxious(焦虑)。只返回 sentiment 和 score(0~1) 的 JSON，如 {"sentiment":"negative","score":0.8}'],
                ['role' => 'user', 'content' => $message],
            ], ['temperature' => 0.1, 'max_tokens' => 50], 'cs_sentiment');

            $content = $result['content'] ?? '';
            if (preg_match('/\{.*?\}/s', $content, $matches)) {
                $parsed = json_decode($matches[0], true);
                if ($parsed && isset($parsed['sentiment'])) {
                    $ruleSentiment = $parsed['sentiment'];
                    $ruleScore = $parsed['score'] ?? $ruleScore;
                }
            }
        } catch (\Throwable $e) {
            // 降级到规则
        }

        $needsPriority = in_array($ruleSentiment, ['negative', 'angry', 'anxious']);

        return ApiResponse::success([
            'sentiment' => $ruleSentiment,
            'score' => round($ruleScore, 2),
            'needs_priority' => $needsPriority,
            'suggest_action' => $needsPriority ? __('app.api.ai_cs.action_priority') : __('app.api.ai_cs.action_normal'),
        ]);
    }

    // ── AI-023: AI 辅助坐席 ──
    public function agentAssist(int $convId, Request $request, LlmService $llm): JsonResponse
    {
        $validated = $request->validate([
            'agent_message' => 'nullable|string|max:500',
            'mode' => 'nullable|in:suggest_reply,knowledge_search,optimize',
        ]);
        $mode = $validated['mode'] ?? 'suggest_reply';
        $agentMsg = $validated['agent_message'] ?? '';

        // 获取最近对话上下文
        $recentMessages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'desc')->take(15)->get()->reverse();
        $contextLines = $recentMessages->map(fn($m) => ($m->sender?->name ?? __('app.api.ai_cs.user')).': '.$m->content)->implode("\n");

        $conv = UserConversation::find($convId);
        $convName = $conv->name ?? __('app.api.ai_cs.conversation');

        try {
            if ($mode === 'suggest_reply') {
                $result = $llm->chat([
                    ['role' => 'system', 'content' => "你是一个客服坐席辅助助手。根据对话上下文，生成3条推荐的客服回复（每条不超过50字），用JSON数组格式返回。回复要专业、友好。"],
                    ['role' => 'user', 'content' => "对话上下文（{$convName}）：\n{$contextLines}"],
                ], ['temperature' => 0.5], 'cs_agent_suggest');

                $content = $result['content'] ?? '[]';
                $suggestions = [];
                if (preg_match('/\[.*?\]/s', $content, $matches)) {
                    $suggestions = json_decode($matches[0], true) ?: [];
                }

                return ApiResponse::success([
                    'mode' => 'suggest_reply',
                    'suggestions' => array_slice($suggestions, 0, 3),
                    'context_summary' => mb_substr($contextLines, 0, 200),
                ]);
            } elseif ($mode === 'optimize' && $agentMsg) {
                $result = $llm->chat([
                    ['role' => 'system', 'content' => '你是一个客服文案优化助手。优化以下客服回复，使其更专业、友好、清晰，保持原意。只返回优化后的文本。'],
                    ['role' => 'user', 'content' => $agentMsg],
                ], ['temperature' => 0.3], 'cs_agent_optimize');

                return ApiResponse::success([
                    'mode' => 'optimize',
                    'original' => $agentMsg,
                    'optimized' => $result['content'] ?? $agentMsg,
                ]);
            } else {
                // knowledge_search: 查找相关 FAQ
                $faqEntries = $this->getFaqEntries();
                $bestFaq = null;
                $bestScore = 0;
                foreach ($faqEntries as $faq) {
                    $score = similar_text(mb_strtolower($contextLines), mb_strtolower($faq['question']), $perc);
                    if ($perc > $bestScore) {
                        $bestScore = $perc;
                        $bestFaq = $faq;
                    }
                }

                return ApiResponse::success([
                    'mode' => 'knowledge_search',
                    'faq' => $bestFaq && $bestScore > 30 ? $bestFaq : null,
                    'confidence' => round($bestScore / 100, 2),
                ]);
            }
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'mode' => $mode,
                'error' => __('app.api.ai_cs.error_unavailable'),
                'suggestions' => [__('app.api.ai_cs.suggest_1'), __('app.api.ai_cs.suggest_2')],
            ]);
        }
    }

    // ── AI-024: AI 会话质检 ──
    public function qualityCheck(int $convId, LlmService $llm): JsonResponse
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->orderBy('created_at', 'asc')->take(50)->get();
        if ($messages->isEmpty()) return ApiResponse::error(__('app.api.ai_cs.no_messages'), 400);

        $lines = $messages->map(fn($m) => ($m->sender?->name ?? __('app.api.ai_cs.user')).': '.$m->content)->implode("\n");

        try {
            $promptService = app(\App\Services\PromptTemplateService::class);
            $qualityPrompt = $promptService->renderByCategory('quality', [
                'agent_messages' => $agentMessages,
                'customer_messages' => $customerMessages,
            ]);
            $result = $llm->chat([
                ['role' => 'system', 'content' => $qualityPrompt ?: '你是一个客服会话质检助手。评估以下客服对话的质量，返回JSON格式（不要多余文字）：{"politeness_score":0~10,"resolution_score":0~10,"response_time_score":0~10,"violations":[],"overall_score":0~10,"suggestions":[],"summary":""}'],
                ['role' => 'user', 'content' => "对话记录：\n{$lines}"],
            ], ['temperature' => 0.2], 'cs_quality');

            $content = $result['content'] ?? '{}';
            if (preg_match('/\{.*?\}/s', $content, $matches)) {
                $check = json_decode($matches[0], true) ?: [];
            } else {
                $check = [];
            }
        } catch (\Throwable $e) {
            $check = [
                'politeness_score' => 7,
                'resolution_score' => 6,
                'response_time_score' => 7,
                'overall_score' => 7,
                'violations' => [],
                'suggestions' => [__('app.api.ai_cs.qa_unavailable')],
                'summary' => __('app.api.ai_cs.qa_summary_fail'),
            ];
        }

        return ApiResponse::success($check);
    }

    // ── AI-025: 多轮对话状态机 ──
    public function dialogStateMachine(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:user_conversations,id',
            'action' => 'required|in:start,process,reset,status',
            'message' => 'nullable|string|max:500',
            'scenario' => 'nullable|in:order_query,refund,complaint,consultation',
        ]);

        $convId = $request->input('conversation_id');
        $action = $request->input('action');
        $message = $request->input('message', '');
        $scenario = $request->input('scenario', 'consultation');

        $cacheKey = "dialog_state:{$convId}";
        $state = Cache::get($cacheKey, ['step' => 0, 'scenario' => $scenario, 'data' => []]);

        if ($action === 'reset') {
            Cache::forget($cacheKey);
            return ApiResponse::success(['step' => 0, 'status' => 'reset', 'message' => __('app.api.ai_cs.dialog_reset')]);
        }

        if ($action === 'status') {
            return ApiResponse::success([
                'step' => $state['step'],
                'scenario' => $state['scenario'],
                'collected' => $state['data'],
                'is_complete' => $this->isScenarioComplete($state),
            ]);
        }

        // start / process
        if ($action === 'start') {
            $state = ['step' => 1, 'scenario' => $scenario, 'data' => []];
        }

        // 收集用户输入
        if ($message) {
            $state['data']['last_input'] = $message;
        }

        // 用 LLM 解析当前步骤和下一步
        $scenarioLabels = [
            'order_query' => __('app.api.ai_cs.scenario_order'),
            'refund' => __('app.api.ai_cs.scenario_refund'),
            'complaint' => __('app.api.ai_cs.scenario_complaint'),
            'consultation' => __('app.api.ai_cs.scenario_consult'),
        ];

        $scenarioFields = [
            'order_query' => ['order_id' => __('app.api.ai_cs.field_order_id'), 'customer_name' => __('app.api.ai_cs.field_customer'), 'issue' => __('app.api.ai_cs.field_issue')],
            'refund' => ['order_id' => __('app.api.ai_cs.field_order_id'), 'reason' => __('app.api.ai_cs.field_reason'), 'amount' => __('app.api.ai_cs.field_amount')],
            'complaint' => ['target' => __('app.api.ai_cs.field_target'), 'reason' => __('app.api.ai_cs.field_complaint_reason'), 'detail' => __('app.api.ai_cs.field_detail')],
            'consultation' => ['question' => __('app.api.ai_cs.field_question'), 'product' => __('app.api.ai_cs.field_product')],
        ];

        $fields = $scenarioFields[$scenario] ?? $scenarioFields['consultation'];
        $collectedInfo = json_encode($state['data'], JSON_UNESCAPED_UNICODE);
        $fieldsDesc = implode("\n", array_map(fn($k, $v) => "- {$k}: {$v}", array_keys($fields), $fields));

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个多轮对话管理助手。场景：{$scenarioLabels[$scenario]}。已收集信息：{$collectedInfo}\n\n需要收集的字段：\n{$fieldsDesc}\n\n判断下一步：1）返回需要用户补充的字段名和提问话术；2）如果所有字段已收集完毕，返回 complete=true 和汇总信息。只返回JSON，格式：{\"next_field\":\"字段名\"|\"complete\",\"prompt\":\"提问话术\",\"summary\":\"汇总信息(complete时)\"}"],
                ['role' => 'user', 'content' => $message ? "用户输入：{$message}" : "开始 {$scenarioLabels[$scenario]} 流程"],
            ], ['temperature' => 0.2], 'cs_dialog_state');

            $content = $result['content'] ?? '{}';
            if (preg_match('/\{.*?\}/s', $content, $matches)) {
                $decision = json_decode($matches[0], true) ?: [];
            } else {
                $decision = [];
            }

            $nextField = $decision['next_field'] ?? 'complete';
            $prompt = $decision['prompt'] ?? __('app.api.ai_cs.need_more_info');
            $summary = $decision['summary'] ?? '';

            if ($nextField === 'complete' || $this->isScenarioComplete($state)) {
                $state['step'] = -1; // 完成
                Cache::put($cacheKey, $state, self::CACHE_TTL);
                return ApiResponse::success([
                    'step' => -1,
                    'is_complete' => true,
                    'summary' => $summary,
                    'collected' => $state['data'],
                    'prompt' => __('app.api.ai_cs.info_complete'),
                ]);
            }

            // 保存状态
            if ($message && $nextField !== 'complete') {
                // 尝试从 LLM 回复中提取字段值
                $state['data'][$nextField] = $message;
            }
            $state['step']++;
            Cache::put($cacheKey, $state, self::CACHE_TTL);

            return ApiResponse::success([
                'step' => $state['step'],
                'is_complete' => false,
                'next_field' => $nextField,
                'prompt' => $prompt,
                'collected' => $state['data'],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::success([
                'step' => $state['step'],
                'is_complete' => false,
                'prompt' => __('app.api.ai_cs.continue_desc'),
                'collected' => $state['data'],
            ]);
        }
    }

    // ── 内部辅助方法 ──

    protected function getFaqEntries(): array
    {
        return Cache::remember('cs_faq_entries', self::CACHE_TTL, function () {
            return [
                ['question' => '如何重置密码？', 'answer' => '请登录后进入「账户设置」→「安全设置」→「修改密码」，或点击登录页「忘记密码」通过邮箱/手机重置。'],
                ['question' => '如何退款？', 'answer' => '在「我的订单」中找到需要退款的订单，点击「申请退款」。退款将在3-7个工作日内原路返回。'],
                ['question' => 'License 如何激活？', 'answer' => '购买后您将收到 License Key，在「License 管理」页面点击「激活」，输入 Key 即可完成激活。'],
                ['question' => '支持哪些支付方式？', 'answer' => '我们支持支付宝、微信支付、银行转账、信用卡（Visa/Mastercard）等多种支付方式。'],
                ['question' => '如何开具发票？', 'answer' => '在「我的订单」中选择需开票的订单，点击「申请发票」，填写发票信息后即可。电子发票将在1-3个工作日发送到邮箱。'],
                ['question' => '产品有哪些版本？', 'answer' => '我们提供免费版、标准版、专业版和企业版四个版本。详细对比请查看「定价」页面。'],
                ['question' => '如何联系技术支持？', 'answer' => '您可以通过在线客服、提交工单或发送邮件至 support@huwutong.com 联系我们的技术支持团队。'],
                ['question' => '支持 API 集成吗？', 'answer' => '是的，我们提供完整的 REST API 和 SDK（支持 PHP/Python/Go/Java/Node.js），详情请查看「开发者文档」。'],
                ['question' => '试用期多久？', 'answer' => '标准版和企业版提供 14 天免费试用，无需绑定支付方式即可开始试用。'],
                ['question' => '如何升级套餐？', 'answer' => '在「订阅管理」页面点击「升级」，选择目标套餐后按提示完成支付即可立即生效。'],
                ['question' => '数据安全如何保障？', 'answer' => '我们采用 AES-256 加密传输和存储，通过 ISO 27001 认证，数据定期备份，确保您的数据安全。'],
                ['question' => '支持多语言吗？', 'answer' => '目前支持中文和英文界面，更多语言版本正在开发中。'],
            ];
        });
    }

    protected function matchFaq(string $message, array $faqEntries): ?array
    {
        $best = null;
        $bestScore = 0;
        foreach ($faqEntries as $faq) {
            similar_text(mb_strtolower($message), mb_strtolower($faq['question']), $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $faq;
            }
        }
        return $bestScore > 40 ? $best : null;
    }

    protected function isExactMatch(string $message, array $faq): bool
    {
        return similar_text(mb_strtolower($message), mb_strtolower($faq['question'])) > 60;
    }

    protected function extractKeywords(string $text): array
    {
        // 简单中文分词：提取长度 ≥2 的连续中文字符和英文单词
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,}|[a-zA-Z]{3,}/u', $text, $matches);
        return array_slice(array_unique($matches[0]), 0, 10);
    }

    protected function fallbackIntent(string $message): string
    {
        $patterns = [
            'complaint' => '/投诉|不满|差评|赔偿|退款|垃圾|恶心/',
            'after_sale' => '/退换|维修|售后|退款|退货/',
            'purchase' => '/买|价格|多少钱|下单|购买|套餐|报价/',
            'technical' => '/故障|报错|错误|无法|不能用|bug|error/',
            'billing' => '/发票|账单|扣费|收费|支付|费用/',
            'account' => '/登录|密码|账号|注册|修改信息/',
            'inquiry' => '/什么|怎么|如何|请问|咨询|功能|支持/',
        ];
        foreach ($patterns as $intent => $pattern) {
            if (preg_match($pattern, $message)) return $intent;
        }
        return 'other';
    }

    protected function routeToGroup(string $intent): string
    {
        $routing = [
            'complaint' => __('app.api.ai_cs.group_complaint'),
            'after_sale' => __('app.api.ai_cs.group_after_sale'),
            'purchase' => __('app.api.ai_cs.group_purchase'),
            'technical' => __('app.api.ai_cs.group_technical'),
            'billing' => __('app.api.ai_cs.group_billing'),
            'account' => __('app.api.ai_cs.group_account'),
            'inquiry' => __('app.api.ai_cs.group_inquiry'),
            'other' => __('app.api.ai_cs.group_other'),
        ];
        return $routing[$intent] ?? __('app.api.ai_cs.group_other');
    }

    protected function isScenarioComplete(array $state): bool
    {
        if ($state['step'] < 0) return true;
        // 简单检查：如果 step >= 所需字段数，视为完成
        $scenario = $state['scenario'] ?? 'consultation';
        $fieldCount = [
            'order_query' => 3,
            'refund' => 3,
            'complaint' => 3,
            'consultation' => 2,
        ];
        return ($state['step'] ?? 0) >= ($fieldCount[$scenario] ?? 3);
    }

    protected function findSimilarCases(string $message, int $convId): array
    {
        // 在同会话中查找历史类似问答
        $history = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get()
            ->reverse();

        $cases = [];
        $prevQuestion = null;
        foreach ($history as $msg) {
            if ($prevQuestion === null) {
                $prevQuestion = $msg->content;
            } else {
                similar_text(mb_strtolower($message), mb_strtolower($prevQuestion), $percent);
                if ($percent > 30) {
                    $cases[] = ['question' => mb_substr($prevQuestion, 0, 100), 'answer' => mb_substr($msg->content, 0, 200)];
                }
                $prevQuestion = null;
            }
        }
        return $cases;
    }
}
