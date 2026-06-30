<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Services\LlmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 多媒体与安全 AI（§4.16 Phase 4）+ 合规标识
 *
 * AI-037 ~ AI-042 + DOM-004 ~ DOM-005 共 8 项功能
 */
class MediaSecurityAIController extends Controller
{
    // ════════════════════════════════════════════
    // AI-037: 图片内容理解
    // ════════════════════════════════════════════
    public function imageAnalysis(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'image_url' => 'nullable|string|max:2000',
            'image_base64' => 'nullable|string',
            'mode' => 'nullable|in:describe,detect_nsfw,ocr,general',
        ]);

        $mode = $request->input('mode', 'general');
        $imageData = $request->input('image_url', '');

        if (empty($imageData) && $request->has('image_base64')) {
            $imageData = '(base64 图片数据: ' . mb_strlen($request->input('image_base64')) . ' chars)';
        }

        if (empty($imageData)) {
            return ApiResponse::error('请提供图片 URL 或 base64 数据', 400);
        }

        $systemPrompts = [
            'describe' => '你是一个图片描述助手。根据图片内容生成详细的中文描述，包括主体、颜色、构图、文字内容等。',
            'detect_nsfw' => '你是一个图片内容审核助手。分析图片是否包含违规内容（色情、暴力、政治敏感等）。返回JSON：{"is_appropriate":true/false,"risk":"low/medium/high","reason":"原因","categories":["违规类别"]}',
            'ocr' => '你是一个图片文字提取助手。识别并提取图片中的所有文字内容，保持原文格式。',
            'general' => '你是一个图片分析助手。全面分析图片内容，包括：主体识别、场景描述、文字识别（如有）、情绪氛围。',
        ];

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => $systemPrompts[$mode] ?? $systemPrompts['general']],
                ['role' => 'user', 'content' => "请分析以下图片：{$imageData}"],
            ], ['temperature' => 0.3], "image_analysis_{$mode}");

            $analysis = $result['content'] ?? '分析失败';

            // 标记 AI 生成内容 (DOM-004)
            $analysis .= "\n\n---\n🔄 *AI 生成内容，仅供参考*";

            return ApiResponse::success([
                'analysis' => $analysis,
                'mode' => $mode,
                'ai_generated' => true, // DOM-004
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('IMAGE_ANALYSIS_ERROR', '图片分析失败: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════
    // AI-038: 视频内容摘要
    // ════════════════════════════════════════════
    public function videoSummary(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'video_url' => 'required|string|max:2000',
            'duration' => 'nullable|integer|min:1|max:36000',
            'transcript' => 'nullable|string|max:50000',
            'language' => 'nullable|string|max:10',
        ]);

        $videoUrl = $request->input('video_url');
        $duration = $request->input('duration', 0);
        $transcript = $request->input('transcript', '');
        $language = $request->input('language', 'zh');

        // 如果没有提供转录文本，尝试模拟提取
        if (empty($transcript)) {
            $transcript = "[视频转录待生成]\n视频来源: {$videoUrl}\n时长: {$duration}秒\n语言: {$language}";
        }

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个视频摘要助手。根据视频转录文本生成结构化摘要：1）视频主题；2）关键内容（时间线/分段）；3）核心观点/结论；4）关键帧描述。视频时长{$duration}秒。使用Markdown格式。"],

                ['role' => 'user', 'content' => "视频转录文本：\n{$transcript}"],
            ], ['temperature' => 0.3], 'video_summary');

            $summary = $result['content'] ?? '摘要生成失败';

            // 估算关键帧（基于时长）
            $keyframes = [];
            if ($duration > 0) {
                $interval = max(30, intdiv($duration, 5));
                for ($t = 0; $t < $duration; $t += $interval) {
                    $keyframes[] = [
                        'time' => gmdate('H:i:s', $t),
                        'timestamp' => $t,
                        'description' => '关键帧 ' . (count($keyframes) + 1),
                    ];
                }
            }

            return ApiResponse::success([
                'summary' => $summary,
                'duration' => $duration,
                'keyframes' => $keyframes,
                'language' => $language,
                'ai_generated' => true,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('VIDEO_SUMMARY_ERROR', '视频摘要生成失败');
        }
    }

    // ════════════════════════════════════════════
    // AI-039: AI 钓鱼/诈骗检测
    // ════════════════════════════════════════════
    public function phishingDetection(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'link_url' => 'nullable|string|max:2000',
            'sender' => 'nullable|string|max:200',
        ]);

        $message = $request->input('message');
        $linkUrl = $request->input('link_url', '');
        $sender = $request->input('sender', '');

        // 规则引擎：快速检测常见钓鱼特征
        $riskFlags = [];
        $riskScore = 0;

        // URL 检测
        if ($linkUrl) {
            if (preg_match('/https?:\/\/(?:[^\s]*\.)?(?:login|secure|account|verify|update|bank|paypal)\.[^\s]+/i', $linkUrl)) {
                $riskFlags[] = '疑似仿冒登录页';
                $riskScore += 25;
            }
            if (preg_match('/https?:\/\/[^\s]*\d+[^\s]*\.(?:com|cn|org)/i', $linkUrl)) {
                if (preg_match('/[^a-zA-Z0-9\-.]/', parse_url($linkUrl, PHP_URL_HOST) ?? '')) {
                    $riskFlags[] = '域名含异常字符';
                    $riskScore += 20;
                }
            }
            if (!preg_match('/^https:/i', $linkUrl)) {
                $riskFlags[] = '非 HTTPS 链接';
                $riskScore += 10;
            }
        }

        // 消息内容检测
        $phishingKeywords = [
            '密码|password|验证码|otp|验证' => 20,
            '紧急|立刻|马上|否则|将被|冻结|限制' => 15,
            '中奖|奖金|免费|领取|红包|优惠' => 15,
            '点击链接|点此|前往|登录|验证身份' => 20,
            '转账|汇款|充值|付款|银行卡|信用卡' => 20,
            '客服|官方客服|安全中心|账户中心' => 10,
            '陌生|好友|推荐|兼职|刷单|赚钱' => 15,
        ];

        foreach ($phishingKeywords as $pattern => $score) {
            if (preg_match('/' . $pattern . '/i', $message)) {
                $riskScore += $score;
            }
        }

        // 发件人检测
        if ($sender && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $sender, $m)) {
            $domain = explode('@', $m[0])[1] ?? '';
            $knownDomains = ['huwutong.com', 'alibaba.com', 'tencent.com', 'baidu.com'];
            $isKnown = false;
            foreach ($knownDomains as $d) {
                if (str_ends_with($domain, $d) && $domain !== $d) {
                    $riskFlags[] = "仿冒域名: {$domain}";
                    $riskScore += 25;
                    $isKnown = true;
                    break;
                }
            }
            if (!$isKnown && !in_array($domain, $knownDomains)) {
                $riskFlags[] = "未知发件域名: {$domain}";
                $riskScore += 5;
            }
        }

        // LLM 增强判断
        $llmRisk = null;
        try {
            $contextInfo = "消息内容：{$message}";
            if ($linkUrl) $contextInfo .= "\n链接：{$linkUrl}";
            if ($sender) $contextInfo .= "\n发件人：{$sender}";

            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个安全检测助手。判断以下消息是否为钓鱼/诈骗/欺诈信息。返回JSON：{"is_phishing":true/false,"confidence":0~1,"reasons":["原因列表"],"risk_level":"low/medium/high"}'],
                ['role' => 'user', 'content' => $contextInfo],
            ], ['temperature' => 0.1], 'phishing_detect');

            $content = $result['content'] ?? '';
            if (preg_match('/\{.*?\}/s', $content, $matches)) {
                $llmRisk = json_decode($matches[0], true);
            }
        } catch (\Throwable $e) {
            // 降级到规则引擎
        }

        // 综合评分
        $finalScore = min(100, $riskScore);
        if ($llmRisk && ($llmRisk['is_phishing'] ?? false)) {
            $finalScore = max($finalScore, ($llmRisk['confidence'] ?? 0.5) * 100);
        }

        $level = 'low';
        if ($finalScore >= 70) $level = 'high';
        elseif ($finalScore >= 40) $level = 'medium';

        return ApiResponse::success([
            'is_phishing' => $finalScore >= 40,
            'risk_score' => round($finalScore, 1),
            'risk_level' => $level,
            'flags' => $riskFlags,
            'llm_analysis' => $llmRisk,
            'recommended_action' => $finalScore >= 70 ? '立即拦截并报告安全团队' : ($finalScore >= 40 ? '标记为可疑，人工审核' : '正常放行'),
            'ai_generated' => true,
        ]);
    }

    // ════════════════════════════════════════════
    // AI-040: PII 智能识别
    // ════════════════════════════════════════════
    public function piiDetection(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:10000',
            'mode' => 'nullable|in:scan,redact,report',
        ]);

        $text = $request->input('text');
        $mode = $request->input('mode', 'scan');

        // 正则规则：快速 PII 检测
        $piiPatterns = [
            '身份证号' => '/[1-9]\d{5}(?:19|20)\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])\d{3}[\dXx]/',
            '手机号' => '/1[3-9]\d{9}/',
            '银行卡号' => '/\b(?:62|60|58|56|55|54|53|52|51|50|49|48|47|46|45|44|43|42|41|40)\d{14,17}\b/',
            '邮箱' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'IP 地址' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '密码' => '/password\s*[=:]\s*\S+|密码\s*[=:：]\s*\S+/i',
        ];

        $findings = [];
        $redactedText = $text;

        foreach ($piiPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                $count = count($matches);
                $examples = array_slice(array_unique(array_column($matches, 0)), 0, 3);
                // 脱敏
                foreach ($examples as $match) {
                    $masked = substr($match, 0, 4) . str_repeat('*', max(4, strlen($match) - 8)) . substr($match, -4);
                    $redactedText = str_replace($match, $masked, $redactedText);
                }
                $findings[] = [
                    'type' => $type,
                    'count' => $count,
                    'examples' => $examples,
                    'risk' => in_array($type, ['身份证号', '银行卡号', '密码']) ? 'high' : 'medium',
                ];
            }
        }

        // LLM 增强检测（检测更复杂的 PII）
        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是一个 PII 检测助手。检查以下文本中是否包含敏感个人信息（身份证、银行卡、密码、密钥、Token、地址等）。返回JSON数组：[{"type":"PII类型","value":"脱敏后的值","position":"位置描述","risk":"high/medium/low"}]。如果无PII返回空数组。'],
                ['role' => 'user', 'content' => mb_substr($text, 0, 2000)],
            ], ['temperature' => 0.1], 'pii_detect');

            $content = $result['content'] ?? '[]';
            if (preg_match('/\[.*?\]/s', $content, $matches)) {
                $llmFindings = json_decode($matches[0], true) ?: [];
                foreach ($llmFindings as $f) {
                    $findings[] = [
                        'type' => $f['type'] ?? '未知',
                        'count' => 1,
                        'examples' => [$f['value'] ?? ''],
                        'risk' => $f['risk'] ?? 'medium',
                        'source' => 'llm',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // 降级到正则结果
        }

        $riskCounts = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($findings as $f) {
            $riskCounts[$f['risk']] = ($riskCounts[$f['risk']] ?? 0) + 1;
        }

        $result = [
            'has_pii' => !empty($findings),
            'total_findings' => count($findings),
            'risk_summary' => $riskCounts,
            'findings' => $findings,
            'risk_level' => ($riskCounts['high'] ?? 0) > 0 ? 'high' : (($riskCounts['medium'] ?? 0) > 0 ? 'medium' : 'low'),
        ];

        if ($mode === 'redact') {
            $result['redacted_text'] = $redactedText;
        }

        return ApiResponse::success($result);
    }

    // ════════════════════════════════════════════
    // AI-041: TTS 语音合成
    // ════════════════════════════════════════════
    public function textToSpeech(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'voice' => 'nullable|in:default,female,male,children,gentle',
            'language' => 'nullable|in:zh,en,auto',
            'speed' => 'nullable|numeric|min:0.5|max:2.0',
        ]);

        $text = $request->input('text');
        $voice = $request->input('voice', 'female');
        $language = $request->input('language', 'zh');
        $speed = $request->input('speed', 1.0);

        $voiceLabels = [
            'default' => '默认音色',
            'female' => '女声（温柔）',
            'male' => '男声（沉稳）',
            'children' => '童声（活泼）',
            'gentle' => '轻柔声',
        ];

        // 字数统计 + 预估时长
        $charCount = mb_strlen($text);
        $estimatedDuration = round($charCount / 4 / $speed, 1); // 每秒4字

        // TTS 需要外部服务集成 (如 Azure TTS / 阿里云语音合成)
        // 此处返回模拟结果 + 集成指引
        $audioUrl = null;
        $ttsAvailable = false;

        // 尝试调用外部 TTS 服务（如果有配置）
        $ttsEndpoint = config('services.tts.endpoint', '');
        $ttsKey = config('services.tts.key', '');

        if ($ttsEndpoint && $ttsKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $ttsKey,
                ])->post($ttsEndpoint, [
                    'text' => $text,
                    'voice' => $voice,
                    'language' => $language === 'auto' ? 'zh' : $language,
                    'speed' => $speed,
                ]);

                if ($response->successful()) {
                    $audioUrl = $response->json('audio_url', '');
                    $ttsAvailable = true;
                }
            } catch (\Throwable $e) {
                Log::warning('[TTS] External service call failed: ' . $e->getMessage());
            }
        }

        return ApiResponse::success([
            'text' => $text,
            'voice' => $voice,
            'voice_label' => $voiceLabels[$voice] ?? '默认',
            'language' => $language,
            'speed' => $speed,
            'char_count' => $charCount,
            'estimated_duration_seconds' => $estimatedDuration,
            'audio_url' => $audioUrl,
            'tts_available' => $ttsAvailable,
            'message' => $audioUrl ? '语音已生成' : 'TTS 服务未配置，请配置 config/services.tts。支持 Azure TTS、阿里云语音合成等。',
            'ai_generated' => true,
        ]);
    }

    // ════════════════════════════════════════════
    // AI-042: 实时同声传译
    // ════════════════════════════════════════════
    public function realtimeTranslation(Request $request, LlmService $llm): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'source_lang' => 'nullable|string|max:10',
            'target_lang' => 'required|string|max:10|in:zh,en,ja,ko,fr,de,es',
            'mode' => 'nullable|in:stream,batch',
        ]);

        $text = $request->input('text');
        $sourceLang = $request->input('source_lang', 'auto');
        $targetLang = $request->input('target_lang', 'zh');
        $mode = $request->input('mode', 'batch');

        $langNames = [
            'zh' => '中文', 'en' => '英文', 'ja' => '日文',
            'ko' => '韩文', 'fr' => '法文', 'de' => '德文', 'es' => '西文',
        ];

        $sourceLabel = $sourceLang === 'auto' ? '自动检测源语言' : ($langNames[$sourceLang] ?? $sourceLang);
        $targetLabel = $langNames[$targetLang] ?? $targetLang;

        try {
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个实时翻译助手。将{$sourceLabel}文本翻译为{$targetLabel}。保持原意和语气，回复只返回翻译结果，不要多余解释。"],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.1], 'realtime_translate');

            $translation = $result['content'] ?? '翻译失败';

            return ApiResponse::success([
                'original' => $text,
                'translation' => $translation,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'target_label' => $langNames[$targetLang] ?? $targetLang,
                'mode' => $mode,
                'detected_lang' => $sourceLang === 'auto' ? $this->detectLanguage($text) : $sourceLang,
                'ai_generated' => true,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('TRANSLATE_ERROR', '翻译服务暂时不可用');
        }
    }

    // ════════════════════════════════════════════
    // DOM-004: 生成式 AI 内容标识
    // ════════════════════════════════════════════
    public function markAIContent(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:50000',
            'source' => 'nullable|string|max:100',
            'add_watermark' => 'nullable|boolean',
        ]);

        $content = $request->input('content');
        $source = $request->input('source', 'AI 助手');
        $addWatermark = $request->input('add_watermark', true);

        // AI 生成内容标识
        $watermark = "\n\n---\n🔄 *此内容由 {$source} 生成" . date(' Y-m-d H:i') . "*";
        if ($addWatermark) {
            // 添加不可见水印（Unicode 零宽字符）
            $hiddenMark = "\u{200B}\u{200C}\u{200D}\u{2060}AI:{$source}:" . date('Ymd');
            $content = $hiddenMark . $content;
        }

        $metadata = [
            'generated_by' => $source,
            'generated_at' => now()->toIso8601String(),
            'model' => config('app.name') . ' AI',
            'content_type' => 'ai_generated',
            'compliance' => 'DOM-004',
        ];

        return ApiResponse::success([
            'content' => $content . ($addWatermark ? $watermark : ''),
            'metadata' => $metadata,
            'has_watermark' => $addWatermark,
            'visible_mark' => $addWatermark ? $watermark : null,
            'compliance_standard' => 'DOM-004 生成式 AI 内容标识',
        ]);
    }

    // ════════════════════════════════════════════
    // DOM-005: 算法/大模型备案支撑
    // ════════════════════════════════════════════
    public function algorithmFiling(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:report,export,check,info',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $action = $request->input('action');
        $dateFrom = $request->input('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        if ($action === 'info') {
            return ApiResponse::success([
                'filing_info' => [
                    'platform' => config('app.name'),
                    'algorithms' => [
                        ['name' => '智能回复建议', 'type' => '生成合成类', 'purpose' => '根据对话上下文生成回复建议', 'status' => '需备案'],
                        ['name' => '聊天内容总结', 'type' => '生成合成类', 'purpose' => '对长对话进行结构化摘要', 'status' => '需备案'],
                        ['name' => '智能客服', 'type' => '对话交互类', 'purpose' => '自动回答用户问题', 'status' => '需备案'],
                        ['name' => '意图识别', 'type' => '分析分类类', 'purpose' => '识别用户意图并路由', 'status' => '需备案'],
                        ['name' => '情感分析', 'type' => '分析分类类', 'purpose' => '分析用户情感倾向', 'status' => '需备案'],
                    ],
                    'applicable_regulations' => [
                        '《互联网信息服务算法推荐管理规定》',
                        '《生成式人工智能服务管理暂行办法》',
                        '《互联网信息服务深度合成管理规定》',
                    ],
                    'filing_authority' => '国家互联网信息办公室',
                    'required_documents' => [
                        '算法备案申请表', '算法安全自评估报告', '算法合规承诺书',
                        '算法原理说明文档', '数据安全管理制度', '用户权益保障方案',
                    ],
                ],
            ]);
        }

        if ($action === 'check') {
            // 检查当前 AI 功能的合规状态
            $features = [
                ['feature' => 'AI-001 智能回复', 'type' => '生成合成', 'needs_filing' => true, 'status' => 'pending'],
                ['feature' => 'AI-003 聊天总结', 'type' => '生成合成', 'needs_filing' => true, 'status' => 'pending'],
                ['feature' => 'AI-019 自动客服', 'type' => '对话交互', 'needs_filing' => true, 'status' => 'pending'],
                ['feature' => 'AI-021 意图识别', 'type' => '分析分类', 'needs_filing' => true, 'status' => 'pending'],
                ['feature' => 'AI-022 情感分析', 'type' => '分析分类', 'needs_filing' => true, 'status' => 'pending'],
            ];

            $needed = count(array_filter($features, fn($f) => $f['needs_filing']));
            $filed = count(array_filter($features, fn($f) => ($f['status'] ?? '') === 'filed'));

            return ApiResponse::success([
                'features' => $features,
                'total_features' => count($features),
                'needs_filing' => $needed,
                'already_filed' => $filed,
                'compliance_rate' => $needed > 0 ? round(($filed / $needed) * 100) : 100,
                'recommendation' => '建议优先完成生成合成类算法的备案申请',
            ]);
        }

        if ($action === 'report') {
            // 生成使用报告
            $totalMessages = ConversationMessage::whereBetween('created_at', [$dateFrom, $dateTo])->count();
            $aiMessages = ConversationMessage::whereIn('message_type', ['ai_reply', 'smart_reply'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])->count();

            return ApiResponse::success([
                'report_date' => now()->toDateString(),
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'total_messages' => $totalMessages,
                'ai_generated_messages' => $aiMessages,
                'ai_ratio' => $totalMessages > 0 ? round(($aiMessages / $totalMessages) * 100, 2) : 0,
                'daily_avg' => $totalMessages > 0 ? round($totalMessages / max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400)) : 0,
                'export_format' => 'CSV/PDF',
                'compliance_standard' => 'DOM-005 算法备案支撑',
            ]);
        }

        // export
        return ApiResponse::success([
            'export_url' => route('api.enterprise-ai.filing-export'),
            'formats' => ['csv', 'pdf', 'json'],
            'data_included' => ['algorithm_list', 'usage_stats', 'safety_assessment', 'user_impact_report'],
            'compliance_standard' => 'DOM-005 算法备案支撑',
        ]);
    }

    // ════════════════════════════════════════════
    // 辅助方法
    // ════════════════════════════════════════════

    protected function detectLanguage(string $text): string
    {
        $zhChars = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text, $matches);
        $enChars = preg_match_all('/[a-zA-Z]/', $text, $matches);
        $jaChars = preg_match_all('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $text, $matches);
        $koChars = preg_match_all('/[\x{AC00}-\x{D7AF}]/u', $text, $matches);

        $total = max($zhChars + $enChars + $jaChars + $koChars, 1);
        $ratios = [
            'zh' => $zhChars / $total,
            'en' => $enChars / $total,
            'ja' => $jaChars / $total,
            'ko' => $koChars / $total,
        ];

        arsort($ratios);
        return key($ratios);
    }
}
