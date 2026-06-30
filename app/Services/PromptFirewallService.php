<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Prompt 安全防火墙 (AI-044 / PRAC-011)
 *
 * 检测 Prompt 注入、隐私窃取、不安全内容，保护 AI 对话安全。
 */
class PromptFirewallService
{
    protected SensitiveWordService $sensitiveWord;

    /** @var array<array{pattern: string, type: string, severity: string, reason: string}> */
    protected array $rules = [];

    public function __construct(SensitiveWordService $sensitiveWord)
    {
        $this->sensitiveWord = $sensitiveWord;
        $this->loadRules();
    }

    /**
     * 加载检测规则
     */
    protected function loadRules(): void
    {
        $this->rules = [
            // ── Prompt 注入指令覆盖 ──
            ['pattern' => '/忽略(?:上述|以上|前面|之前).*(?:指令|命令|要求|规则|设定)/ui', 'type' => 'injection', 'severity' => 'high', 'reason' => '试图覆盖系统指令'],
            ['pattern' => '/忘记\s*(?:你(?:是|的)|所有|之前)/ui', 'type' => 'injection', 'severity' => 'high', 'reason' => '试图清除 AI 身份设定'],
            ['pattern' => '/你(?:现在|接下来)(?:是|扮演|充当|作为)\s*(?!AI|助手|机器人)/ui', 'type' => 'injection', 'severity' => 'medium', 'reason' => '角色扮演攻击'],
            ['pattern' => '/Ignore\s+(?:all\s+)?(?:previous|above|prior)\s+(?:instructions|prompts|directives)/ui', 'type' => 'injection', 'severity' => 'high', 'reason' => '英文指令覆盖攻击'],
            ['pattern' => '/你是\s*(?:GPT|ChatGPT|Claude|LLM|OpenAI|Anthropic)/ui', 'type' => 'injection', 'severity' => 'medium', 'reason' => '试图泄露模型身份'],
            ['pattern' => '/(?:重复|复述|输出|显示).*(?:上面|以上|system|prompt|指令|设定)/ui', 'type' => 'injection', 'severity' => 'high', 'reason' => 'Prompt 泄露攻击'],

            // ── 隐私窃取 ──
            ['pattern' => '/(?:所有|全部|每个)(?:用户|客户|账号|手机|邮箱|密码)/ui', 'type' => 'privacy', 'severity' => 'high', 'reason' => '试图批量获取用户数据'],
            ['pattern' => '/(?:给我|查询|获取|导出).*(?:手机号?|电话号码?|邮箱|密码|身份证|银行卡)/ui', 'type' => 'privacy', 'severity' => 'critical', 'reason' => '试图获取个人敏感信息'],
            ['pattern' => '/(?:数据库|DB|数据表|表结构|migration).*(?:密码|password|secret|key|token)/ui', 'type' => 'privacy', 'severity' => 'critical', 'reason' => '试图获取系统凭据'],
            ['pattern' => '/sql\s+(?:注入|drop|delete|truncate|exec)/ui', 'type' => 'privacy', 'severity' => 'critical', 'reason' => 'SQL 注入攻击'],

            // ── 不安全内容 ──
            ['pattern' => '/(?:如何|怎样|怎么)\s*(?:制作|制造|合成|提取)\s*(?:毒品|炸弹|武器|毒药|炸药)/ui', 'type' => 'unsafe', 'severity' => 'critical', 'reason' => '危险品制作请求'],
            ['pattern' => '/(?:钓鱼|诈骗|冒充|伪造).*(?:网站|邮件|链接|短信)/ui', 'type' => 'unsafe', 'severity' => 'high', 'reason' => '网络欺诈内容'],
        ];
    }

    /**
     * 全面检查消息安全性
     *
     * @return array{blocked: bool, reason: ?string, issues: array}
     */
    public function inspect(string $content): array
    {
        if (empty(trim($content))) {
            return ['blocked' => false, 'reason' => null, 'issues' => []];
        }

        $issues = [];

        // 1. 规则匹配检测
        foreach ($this->rules as $rule) {
            if (preg_match($rule['pattern'], $content)) {
                $issues[] = [
                    'type' => $rule['type'],
                    'severity' => $rule['severity'],
                    'reason' => $rule['reason'],
                    'match' => $this->extractMatch($content, $rule['pattern']),
                ];
            }
        }

        // 2. 敏感词检测
        $sensitiveResult = $this->sensitiveWord->check($content);
        if ($sensitiveResult['hasSensitive']) {
            $issues[] = [
                'type' => 'sensitive_word',
                'severity' => 'medium',
                'reason' => '包含敏感词: ' . implode(', ', $sensitiveResult['matched']),
                'match' => $sensitiveResult['matched'],
            ];
        }

        // 3. 判断是否拦截
        $criticalIssues = array_filter($issues, fn($i) => $i['severity'] === 'critical');
        $highIssues = array_filter($issues, fn($i) => $i['severity'] === 'high');

        $blocked = !empty($criticalIssues) || count($highIssues) >= 2;

        if ($blocked) {
            Log::warning('[PromptFirewall] 已拦截不安全内容', [
                'issues' => $issues,
                'content_preview' => mb_substr($content, 0, 100),
            ]);
        }

        return [
            'blocked' => $blocked,
            'reason' => $blocked ? ($issues[0]['reason'] ?? '触发安全策略') : null,
            'issues' => $issues,
        ];
    }

    /**
     * 提取匹配片段用于日志
     */
    protected function extractMatch(string $content, string $pattern): string
    {
        if (preg_match($pattern, $content, $m)) {
            return mb_substr($m[0], 0, 80);
        }
        return '';
    }
}
