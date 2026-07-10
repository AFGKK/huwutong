<?php

namespace App\Services;

use App\Support\DbSql;
use App\Services\TextToSqlGuardService;
use Illuminate\Support\Facades\Log;

/**
 * AI 运营分析助手服务 (M2-42)
 *
 * 基于 Text-to-SQL 安全护栏，提供面向运营人员的自然语言业务分析能力。
 * 支持预置分析模板、自动图表类型推荐、智能问答。
 */
class AIOpsAnalystService
{
    private array $templateSqlMap = [];

    public function __construct(
        protected TextToSqlGuardService $guardService,
        protected LlmService $llmService,
    ) {
        $this->templateSqlMap = config('ai-ops.templates', []);
    }

    /**
     * 获取预置分析模板列表
     */
    public function getTemplates(): array
    {
        return config('ai-ops.templates', []);
    }

    /**
     * 按分类获取模板
     */
    public function getTemplatesByCategory(?string $category = null): array
    {
        $templates = config('ai-ops.templates', []);
        if ($category) {
            return array_values(array_filter($templates, fn($t) => ($t['category'] ?? '') === $category));
        }
        return $templates;
    }

    /**
     * 根据模板 key 获取预置分析数据
     */
    public function queryTemplate(string $key, array $params = []): array
    {
        $sql = $this->buildTemplateSql($key, $params);
        if (!$sql) {
            return ['success' => false, 'error' => '未知的分析模板'];
        }

        return $this->executeAnalysis($sql, $this->getChartTypeForTemplate($key));
    }

    /**
     * 自然语言运营问题分析
     */
    public function askQuestion(string $question, array $context = []): array
    {
        $userContext = $context['user'] ?? [];
        $dbContext = $context['db_context'] ?? '';

        // 1. 检查是否匹配预置模板
        $matched = $this->matchTemplate($question);
        if ($matched) {
            return $this->queryTemplate($matched['key'], $matched['params'] ?? []);
        }

        // 2. 通过 LLM 生成分析 SQL
        $messages = [
            ['role' => 'system', 'content' => $this->buildAnalystPrompt($dbContext)],
            ['role' => 'user', 'content' => $question],
        ];

        try {
            $llmResponse = $this->llmService->driver()->chat($messages, [
                'temperature' => config('ai-ops.llm.temperature', 0.1),
                'max_tokens' => config('ai-ops.llm.max_tokens', 1000),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Ops: LLM call failed', ['question' => $question, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'AI 服务暂时不可用', 'llm_error' => true];
        }

        $responseText = $llmResponse['content'] ?? $llmResponse['response'] ?? '';
        $parsed = $this->parseResponse($responseText);

        if (!$parsed || empty($parsed['sql'])) {
            return [
                'success' => false,
                'error' => $parsed['explanation'] ?? '无法理解您的分析需求',
                'llm_raw' => $responseText,
            ];
        }

        // 3. 安全护栏验证
        $validation = $this->guardService->validate($parsed['sql'], $userContext);
        if (!$validation['allowed']) {
            Log::warning('AI Ops: SQL guard rejected', ['question' => $question, 'reason' => $validation['reason']]);
            return ['success' => false, 'error' => $validation['reason'], 'blocked' => true];
        }

        // 4. 执行查询
        $result = $this->guardService->execute($validation['sql'], [], $userContext);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? '查询执行失败'];
        }

        // 5. 确定图表类型和返回数据
        $chartType = $this->detectChartType($parsed['sql'], $parsed['chart'] ?? 'auto', $result['data'] ?? []);

        return [
            'success' => true,
            'data' => $result['data'] ?? [],
            'count' => $result['count'] ?? 0,
            'sql' => $validation['sql'],
            'explanation' => $parsed['explanation'] ?? '',
            'chart_type' => $chartType,
            'elapsed_ms' => $result['elapsed_ms'] ?? 0,
        ];
    }

    /**
     * 分析看板概览数据
     */
    public function getDashboardOverview(): array
    {
        $userContext = ['role' => 'admin'];

        $metrics = [];

        // License 总数
        $r1 = $this->guardService->execute('SELECT COUNT(*) as total FROM licenses', [], $userContext);
        if ($r1['success']) $metrics['total_licenses'] = $r1['data'][0]['total'] ?? 0;

        // 活跃 License
        $r2 = $this->guardService->execute("SELECT COUNT(*) as total FROM licenses WHERE status = 'active'", [], $userContext);
        if ($r2['success']) $metrics['active_licenses'] = $r2['data'][0]['total'] ?? 0;

        // 客户总数
        $r3 = $this->guardService->execute('SELECT COUNT(*) as total FROM customers', [], $userContext);
        if ($r3['success']) $metrics['total_customers'] = $r3['data'][0]['total'] ?? 0;

        // 今日激活
        $todaySql = DbSql::aiOpsTemplateSql()['today_activations'] ?? null;
        if ($todaySql) {
            $r4 = $this->guardService->execute($todaySql, [], $userContext);
            if ($r4['success']) {
                $metrics['today_activations'] = $r4['data'][0]['total'] ?? 0;
            }
        }

        // 即将过期（7天内）
        $expiringSql = DbSql::aiOpsTemplateSql()['expiring_soon'] ?? null;
        if ($expiringSql) {
            $r5 = $this->guardService->execute(
                'SELECT COUNT(*) as total FROM licenses WHERE status = \'active\' AND expires_at BETWEEN NOW() AND '.DbSql::addDaysToNow(7),
                [],
                $userContext
            );
            if ($r5['success']) {
                $metrics['expiring_soon'] = $r5['data'][0]['total'] ?? 0;
            }
        }

        return $metrics;
    }

    /**
     * 构建模板 SQL
     */
    private function buildTemplateSql(string $key, array $params = []): ?string
    {
        $days = $params['days'] ?? 30;
        $limit = $params['limit'] ?? 10;

        $sqlMap = array_merge(DbSql::aiOpsTemplateSql($days, $limit), [
            'activation_by_product' => "SELECT p.name as product, COUNT(*) as total FROM license_activations la JOIN licenses l ON la.license_id = l.id JOIN products p ON l.product_id = p.id GROUP BY p.id, p.name ORDER BY total DESC LIMIT {$limit}",
            'license_status_dist' => 'SELECT status, COUNT(*) as total FROM licenses GROUP BY status ORDER BY total DESC',
            'top_customers' => "SELECT c.name, c.email, COUNT(l.id) as license_count FROM customers c LEFT JOIN licenses l ON l.customer_id = c.id GROUP BY c.id, c.name, c.email ORDER BY license_count DESC LIMIT {$limit}",
            'device_by_platform' => 'SELECT platform, COUNT(*) as total FROM devices GROUP BY platform ORDER BY total DESC',
            'subscription_by_plan' => "SELECT plan, COUNT(*) as total FROM subscriptions WHERE status = 'active' GROUP BY plan ORDER BY total DESC",
        ]);

        return $sqlMap[$key] ?? null;
    }

    /**
     * 为模板指定图表类型
     */
    private function getChartTypeForTemplate(string $key): string
    {
        return match ($key) {
            'activation_trend', 'customer_growth', 'active_devices', 'mrr_trend' => 'line',
            'activation_by_product', 'top_customers', 'device_by_platform', 'subscription_by_plan', 'revenue_by_product' => 'bar',
            'license_status_dist', 'geo_distribution' => 'pie',
            'expiring_soon' => 'table',
            'revenue_summary', 'churn_rate' => 'number',
            default => 'table',
        };
    }

    /**
     * 尝试匹配问题到预置模板
     */
    private function matchTemplate(string $question): ?array
    {
        $keywords = [
            'activation_trend' => ['激活趋势', '激活走势', '激活统计', '激活数量', 'activation'],
            'activation_by_product' => ['按产品', '产品激活', '各产品'],
            'license_status_dist' => ['状态分布', '各状态', 'license状态'],
            'expiring_soon' => ['即将过期', '快过期', '7天内过期'],
            'top_customers' => ['客户排行', '客户排名', '最多license', '大客户'],
            'customer_growth' => ['客户增长', '新增客户', '客户趋势', '新客户'],
            'device_by_platform' => ['设备平台', '操作系统', '平台分布'],
            'active_devices' => ['活跃设备', '设备活跃'],
            'subscription_by_plan' => ['订阅方案', '方案分布', '套餐分布'],
            'mrr_trend' => ['mrr', '收入趋势', '经常性收入'],
            'churn_rate' => ['流失率', 'churn'],
            'revenue_summary' => ['收入概览', '总收入', '本月收入'],
        ];

        foreach ($keywords as $key => $words) {
            foreach ($words as $word) {
                if (mb_stripos($question, $word) !== false) {
                    return ['key' => $key, 'params' => []];
                }
            }
        }

        return null;
    }

    /**
     * 构建运营分析助手的 system prompt
     */
    private function buildAnalystPrompt(string $dbContext): string
    {
        $allowedTables = implode(', ', config('ai-ops.allowed_tables', ['licenses', 'customers', 'devices']));

        return <<<PROMPT
你是一个专业的数据运营分析助手。你只能生成 SELECT 查询语句。

可用表：{$allowedTables}

规则：
1. 只生成 SELECT 查询
2. 结果限制最多 100 条
3. 返回 JSON 格式：{"sql": "SELECT ...", "explanation": "分析说明", "chart": "推荐图表类型"}
4. chart 可选值：bar(柱状), line(折线), pie(饼图), table(表格), number(数值)
5. 时间序列数据推荐 line，分类对比推荐 bar，占比推荐 pie
6. 使用中文解释
7. 默认按时间倒序排列
{$dbContext}
PROMPT;
    }

    /**
     * 解析 LLM 返回
     */
    private function parseResponse(string $text): ?array
    {
        // 尝试提取 JSON
        $text = trim($text);
        $start = mb_strpos($text, '{');
        $end = mb_strrpos($text, '}');
        if ($start !== false && $end !== false) {
            $json = mb_substr($text, $start, $end - $start + 1);
            $parsed = json_decode($json, true);
            if ($parsed) return $parsed;
        }

        // 直接解析
        $parsed = json_decode($text, true);
        return $parsed ?: null;
    }

    /**
     * 检测图表类型
     */
    private function detectChartType(string $sql, string $preferred, array $data): string
    {
        if ($preferred !== 'auto') return $preferred;

        $sqlUpper = strtoupper($sql);

        // 时间序列 → 折线图
        if (preg_match('/DATE\(|DATE_FORMAT|YEAR\(|MONTH\(|DAY\(/', $sqlUpper)) {
            return 'line';
        }

        // COUNT + GROUP BY → 柱状图或饼图
        if (preg_match('/COUNT\(.*\)\s+(AS\s+\w+\s+)?,\s*\w/', $sqlUpper) || preg_match('/GROUP\s+BY/i', $sqlUpper)) {
            if (count($data) <= 7) return 'pie';
            return 'bar';
        }

        // 单数值 → 数值卡片
        if (count($data) === 1 && count((array)$data[0]) === 1) {
            return 'number';
        }

        return 'table';
    }

    /**
     * 执行分析查询（带图表类型）
     */
    private function executeAnalysis(string $sql, string $chartType): array
    {
        $result = $this->guardService->execute($sql, [], ['role' => 'admin']);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? '查询失败'];
        }

        return [
            'success' => true,
            'data' => $result['data'] ?? [],
            'count' => $result['count'] ?? 0,
            'sql' => $sql,
            'chart_type' => $chartType,
            'elapsed_ms' => $result['elapsed_ms'] ?? 0,
        ];
    }
}
