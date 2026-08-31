<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\LlmService;
use App\Services\TextToSqlGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Text-to-SQL 安全护栏控制器 (M2-136)
 *
 * 提供自然语言转 SQL 的安全执行入口：
 * - LLM 生成 SQL
 * - 安全护栏多层校验
 * - 结果返回
 */
class TextToSqlController extends Controller
{
    const SYSTEM_PROMPT = <<<'PROMPT'
你是一个专业的 SQL 查询助手。你只能生成 SELECT 查询语句。

规则：
1. 只生成 SELECT 查询，绝不生成 INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE
2. 只使用以下表：请根据用户问题自动识别表
3. 结果限制最多 100 条
4. 返回格式为 JSON: {"sql": "SELECT ...", "explanation": "简短解释"}
5. 如果你认为这个查询不安全或涉及敏感数据，返回 {"sql": null, "explanation": "拒绝原因"}
6. 不要使用通配符 SELECT *（除非用户明确要求所有字段）
7. 使用中文解释你的查询意图
PROMPT;

    public function __construct(
        protected TextToSqlGuardService $guardService,
        protected LlmService $llmService,
    ) {}

    /**
     * 自然语言转 SQL 并安全执行
     *
     * POST /api/text-to-sql/query
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:1000',
            'context' => 'nullable|string|max:500', // 额外的数据库上下文信息
        ]);

        $question = $validated['question'];
        $dbContext = $validated['context'] ?? '';

        // 构建 LLM 请求
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($dbContext)],
            ['role' => 'user', 'content' => $question],
        ];

        // 调用 LLM 生成 SQL
        try {
            $llmResponse = $this->llmService->driver()->chat($messages, [
                'temperature' => 0.1,
                'max_tokens' => 500,
            ]);
        } catch (\Throwable $e) {
            Log::error('Text-to-SQL LLM 调用失败', [
                'question' => $question,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('LLM_ERROR', __("app.text_to_sql.msg_9363738d"), 503);
        }

        $responseText = $llmResponse['content'] ?? $llmResponse['response'] ?? '';

        // 解析 LLM 返回的 JSON
        $parsed = $this->parseLlmResponse($responseText);
        if (! $parsed || empty($parsed['sql'])) {
            return ApiResponse::error('INVALID_LLM_RESPONSE', $parsed['explanation'] ?? __("app.text_to_sql.msg_9597e68c"), 422);
        }

        $sql = $parsed['sql'];
        $explanation = $parsed['explanation'] ?? '';

        // 安全护栏验证
        $userContext = [
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
            'role' => $request->user()?->getRoleNames()?->first(),
        ];

        $validation = $this->guardService->validate($sql, $userContext);
        if (! $validation['allowed']) {
            Log::warning('Text-to-SQL 安全护栏拦截', [
                'question' => $question,
                'sql' => $sql,
                'reason' => $validation['reason'],
            ]);
            return ApiResponse::error('SQL_GUARD_REJECTED', $validation['reason'], 403);
        }

        // 执行查询
        $result = $this->guardService->execute($validation['sql'], [], $userContext);
        if (! $result['success']) {
            return ApiResponse::error('QUERY_FAILED', $result['error'], 500);
        }

        return ApiResponse::success([
            'sql' => $validation['sql'],
            'explanation' => $explanation,
            'data' => $result['data'],
            'count' => $result['count'],
            'elapsed_ms' => $result['elapsed_ms'],
            'warnings' => $result['warnings'],
        ], __('app.text_to_sql.query_executed'));
    }

    /**
     * 直接执行 SQL（仅用于管理后台，已有安全护栏保护）
     *
     * POST /api/text-to-sql/execute
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sql' => 'required|string|max:5000',
        ]);

        $userContext = [
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
            'role' => $request->user()?->getRoleNames()?->first(),
        ];

        // 安全验证
        $validation = $this->guardService->validate($validated['sql'], $userContext);
        if (! $validation['allowed']) {
            return ApiResponse::error('SQL_GUARD_REJECTED', $validation['reason'], 403);
        }

        $result = $this->guardService->execute($validation['sql'], [], $userContext);
        if (! $result['success']) {
            return ApiResponse::error('QUERY_FAILED', $result['error'], 500);
        }

        return ApiResponse::success([
            'sql' => $validation['sql'],
            'data' => $result['data'],
            'count' => $result['count'],
            'elapsed_ms' => $result['elapsed_ms'],
            'warnings' => $result['warnings'],
        ], __('app.text_to_sql.query_executed'));
    }

    /**
     * 获取安全护栏配置
     *
     * GET /api/text-to-sql/config
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success($this->guardService->getConfigSummary());
    }

    /**
     * 测试 SQL 验证（不执行查询）
     *
     * POST /api/text-to-sql/validate
     */
    public function validateSql(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sql' => 'required|string|max:5000',
        ]);

        $userContext = [
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
        ];

        $result = $this->guardService->validate($validated['sql'], $userContext);

        if (! $result['allowed']) {
            return ApiResponse::success([
                'allowed' => false,
                'reason' => $result['reason'],
                'sql' => $result['sql'],
            ]);
        }

        return ApiResponse::success([
            'allowed' => true,
            'sql' => $result['sql'],
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * 构建系统提示词
     */
    protected function buildSystemPrompt(string $dbContext): string
    {
        $prompt = self::SYSTEM_PROMPT;

        if ($dbContext) {
            $prompt .= "\n\n数据库上下文信息：\n{$dbContext}";
        }

        return $prompt;
    }

    /**
     * 解析 LLM 返回的 JSON 响应
     */
    protected function parseLlmResponse(string $response): ?array
    {
        // 尝试提取 JSON
        if (preg_match('/\{[^{}]*"sql"[^{}]*\}/i', $response, $matches)) {
            $json = $matches[0];
        } else {
            $json = $response;
        }

        // 尝试解析 JSON
        $parsed = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果 LLM 直接返回了 SQL 语句而不是 JSON
            if (preg_match('/^SELECT\b/i', trim($response))) {
                return [
                    'sql' => trim($response),
                    'explanation' => 'AI 生成的 SQL 查询',
                ];
            }
            return null;
        }

        return $parsed;
    }
}
