<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\License;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI 对话引擎 — 意图识别
 *
 * 识别用户问题的意图和提取关键实体（槽位）
 */
class IntentRecognizer
{
    /**
     * 已知意图列表
     */
    private const INTENTS = [
        'activate_license' => [
            'patterns' => ['激活', '激活授权', '激活License', '激活许可', '怎么激活', '如何激活'],
            'entities' => ['license_key', 'device_fingerprint'],
            'description' => 'License 激活相关',
        ],
        'check_license' => [
            'patterns' => ['查', '查询', '查看', '状态', '有效期', '什么时候到期', '还有多久'],
            'entities' => ['license_key'],
            'description' => '查询 License 状态',
        ],
        'device_management' => [
            'patterns' => ['设备', '解绑', '换机', '换电脑', '绑定设备', '设备数量'],
            'entities' => ['license_key', 'device_fingerprint'],
            'description' => '设备管理相关',
        ],
        'renewal' => [
            'patterns' => ['续费', '续期', '延期', '延长', 'renew'],
            'entities' => ['license_key', 'subscription_id'],
            'description' => '续费相关',
        ],
        'trial' => [
            'patterns' => ['试用', '免费试用', '体验', 'trial'],
            'entities' => ['product_name'],
            'description' => '试用相关',
        ],
        'faq_activation' => [
            'patterns' => ['激活失败', '无法激活', '激活不了', '激活错误'],
            'entities' => ['license_key', 'error_code'],
            'description' => '激活失败故障排查',
        ],
        'faq_general' => [
            'patterns' => ['怎么用', '如何使用', '什么是', '支持', '文档', '帮助'],
            'entities' => [],
            'description' => '通用 FAQ 咨询',
        ],
        'billing_info' => [
            'patterns' => ['收费', '价格', '多少钱', '套餐', '费用', '账单', '付款'],
            'entities' => ['product_name'],
            'description' => '计费相关咨询',
        ],
        'contact_human' => [
            'patterns' => ['人工', '客服', '转人工', '找人', '技术人员', '联系'],
            'entities' => [],
            'description' => '转人工客服',
        ],
        'greeting' => [
            'patterns' => ['你好', '您好', '嗨', 'hi', 'hello', 'hey', '在吗'],
            'entities' => [],
            'description' => '打招呼',
        ],
    ];

    /**
     * 识别意图
     */
    public function recognize(string $message, array $conversationHistory = []): array
    {
        $lowerMsg = mb_strtolower($message);

        // 1. 检查是否匹配已知意图
        $matchedIntents = [];
        foreach (self::INTENTS as $intent => $config) {
            $score = $this->matchPatterns($lowerMsg, $config['patterns']);
            if ($score > 0) {
                $matchedIntents[] = [
                    'intent' => $intent,
                    'score' => $score,
                    'description' => $config['description'],
                ];
            }
        }

        // 2. 按匹配分数排序
        usort($matchedIntents, fn($a, $b) => $b['score'] <=> $a['score']);

        $topIntent = $matchedIntents[0]['intent'] ?? 'general_query';

        // 3. 提取实体
        $entities = $this->extractEntities($message, $topIntent);

        // 4. 根据历史优化意图
        $topIntent = $this->refineWithHistory($topIntent, $entities, $conversationHistory);

        return [
            'intent' => $topIntent,
            'confidence' => $matchedIntents[0]['score'] ?? 0.3,
            'entities' => $entities,
            'all_matches' => $matchedIntents,
        ];
    }

    /**
     * 检查是否需要转人工
     */
    public function shouldEscalate(array $intentResult): bool
    {
        // 用户明确要求转人工
        if ($intentResult['intent'] === 'contact_human') {
            return true;
        }

        // 置信度过低
        if ($intentResult['confidence'] < 0.3) {
            return true;
        }

        return false;
    }

    /**
     * 匹配模式（返回 0-1 的分数）
     */
    protected function matchPatterns(string $message, array $patterns): float
    {
        $maxScore = 0;

        foreach ($patterns as $pattern) {
            $lowerPattern = mb_strtolower($pattern);
            if (mb_strpos($message, $lowerPattern) !== false) {
                // 精确匹配加分
                $score = mb_strlen($pattern) / max(mb_strlen($message), 1);
                $maxScore = max($maxScore, min($score * 2, 0.95));
            }
        }

        return $maxScore;
    }

    /**
     * 提取实体（槽位填充）
     */
    protected function extractEntities(string $message, string $intent): array
    {
        $entities = [];

        // 提取 License Key
        if (preg_match('/HWT-[\w-]+/i', $message, $matches)) {
            $entities['license_key'] = strtoupper($matches[0]);
        }

        // 提取错误码
        if (preg_match('/[A-Z_]{5,50}/', $message, $matches)) {
            $enum = ApiErrorCode::tryFrom($matches[0]);
            if ($enum) {
                $entities['error_code'] = $matches[0];
            }
        }

        // 提取产品名
        $productKeywords = ['基础版', '专业版', '企业版', 'Pro', 'Basic', 'Enterprise'];
        foreach ($productKeywords as $keyword) {
            if (mb_stripos($message, $keyword) !== false) {
                $entities['product_name'] = $keyword;
                break;
            }
        }

        // 提取数字（设备数量、金额等）
        if (preg_match('/(\d+)\s*台/', $message, $matches)) {
            $entities['device_count'] = (int) $matches[1];
        }

        return $entities;
    }

    /**
     * 根据对话历史优化意图
     */
    protected function refineWithHistory(string $intent, array $entities, array $history): string
    {
        // 如果当前查询没有直接意图，且历史中有明确的意图，则沿用
        if ($intent === 'general_query' && !empty($history)) {
            $lastIntent = $history[0]['intent'] ?? null;
            if ($lastIntent && $lastIntent !== 'general_query') {
                return $lastIntent;
            }
        }

        return $intent;
    }

    /**
     * 获取所有意图列表
     */
    public function getIntents(): array
    {
        return self::INTENTS;
    }
}
