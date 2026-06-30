<?php

namespace App\Services;

use App\Models\WebhookEndpoint;
use App\Models\WebhookFilter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 条件化 Webhook 过滤器服务 (M2-53)
 *
 * 按事件类型/产品/客户/状态筛选 + 自定义Payload模板 + 批量测试
 */
class WebhookFilterService
{
    /**
     * 获取端点的过滤器列表
     */
    public function getFilters(int $endpointId): array
    {
        return WebhookFilter::where('webhook_endpoint_id', $endpointId)
            ->byPriority()
            ->get()
            ->toArray();
    }

    /**
     * 创建过滤器
     */
    public function createFilter(int $endpointId, array $data, ?int $userId = null): WebhookFilter
    {
        $endpoint = WebhookEndpoint::findOrFail($endpointId);

        // 检查数量上限
        $count = WebhookFilter::where('webhook_endpoint_id', $endpointId)->count();
        $max = config('webhook-filter.max_filters_per_endpoint', 20);
        if ($count >= $max) {
            throw new \RuntimeException("过滤器已达上限 ({$max}个)");
        }

        return WebhookFilter::create([
            'webhook_endpoint_id' => $endpointId,
            'name' => $data['name'],
            'conditions' => $data['conditions'],
            'match_type' => $data['match_type'] ?? 'all',
            'payload_template' => $data['payload_template'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 0,
            'created_by' => $userId,
        ]);
    }

    /**
     * 更新过滤器
     */
    public function updateFilter(int $id, array $data): WebhookFilter
    {
        $filter = WebhookFilter::findOrFail($id);
        $filter->update($data);
        return $filter->fresh();
    }

    /**
     * 删除过滤器
     */
    public function deleteFilter(int $id): bool
    {
        return WebhookFilter::findOrFail($id)->delete() ? true : false;
    }

    /**
     * 检查事件是否匹配过滤条件 (核心方法)
     *
     * @param WebhookFilter $filter
     * @param string $eventType
     * @param array $payload
     * @return bool
     */
    public function matches(WebhookFilter $filter, string $eventType, array $payload): bool
    {
        if (!$filter->is_active) {
            return false;
        }

        $conditions = $filter->conditions ?? [];
        if (empty($conditions)) {
            return true; // 无条件则默认通过
        }

        $matchType = $filter->match_type ?? 'all';
        $results = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $expectedValue = $condition['value'] ?? '';

            // 特殊处理 event_type 字段
            if ($field === 'event_type') {
                $actualValue = $eventType;
            } else {
                $actualValue = $this->getFieldValue($payload, $field);
            }

            $matched = $this->evaluateCondition($actualValue, $operator, $expectedValue);
            $results[] = $matched;
        }

        if ($matchType === 'any') {
            return in_array(true, $results, true);
        }

        // 'all' (AND)
        return !in_array(false, $results, true);
    }

    /**
     * 从嵌套数组中按点号路径取值
     */
    protected function getFieldValue(array $data, string $fieldPath): mixed
    {
        $keys = explode('.', $fieldPath);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * 计算单个条件
     */
    protected function evaluateCondition(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        return match ($operator) {
            'equals' => $actualValue == $expectedValue,
            'not_equals' => $actualValue != $expectedValue,
            'contains' => is_string($actualValue) && str_contains($actualValue, (string)$expectedValue),
            'not_contains' => is_string($actualValue) && !str_contains($actualValue, (string)$expectedValue),
            'starts_with' => is_string($actualValue) && str_starts_with($actualValue, (string)$expectedValue),
            'ends_with' => is_string($actualValue) && str_ends_with($actualValue, (string)$expectedValue),
            'in' => is_array($expectedValue) ? in_array($actualValue, $expectedValue) : (is_string($expectedValue) && in_array($actualValue, explode(',', $expectedValue))),
            'not_in' => is_array($expectedValue) ? !in_array($actualValue, $expectedValue) : (is_string($expectedValue) && !in_array($actualValue, explode(',', $expectedValue))),
            'greater_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue > $expectedValue,
            'less_than' => is_numeric($actualValue) && is_numeric($expectedValue) && $actualValue < $expectedValue,
            'exists' => $actualValue !== null,
            'not_exists' => $actualValue === null,
            'regex' => is_string($actualValue) && preg_match('/' . $expectedValue . '/', $actualValue) === 1,
            default => true,
        };
    }

    /**
     * 应用 Payload 模板
     */
    public function applyTemplate(?array $template, array $payload, string $eventType): array
    {
        if (empty($template)) {
            return $payload;
        }

        $result = [];
        foreach ($template as $key => $value) {
            if (is_string($value)) {
                // 替换模板变量
                $resolved = $this->resolveTemplateVars($value, $payload, $eventType);
                $result[$key] = $resolved;
            } elseif (is_array($value)) {
                $result[$key] = $this->applyTemplate($value, $payload, $eventType);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 解析模板变量
     */
    protected function resolveTemplateVars(string $template, array $payload, string $eventType): string
    {
        $vars = [
            '{{event_type}}' => $eventType,
            '{{event_time}}' => now()->toIso8601String(),
            '{{tenant_id}}' => $payload['tenant_id'] ?? '',
            '{{license.id}}' => $payload['license']['id'] ?? '',
            '{{license.key}}' => $payload['license']['license_key'] ?? ($payload['license']['key'] ?? ''),
            '{{license.status}}' => $payload['license']['status'] ?? '',
            '{{license.expires_at}}' => $payload['license']['expires_at'] ?? '',
            '{{customer.id}}' => $payload['customer']['id'] ?? ($payload['license']['customer_id'] ?? ''),
            '{{customer.name}}' => $payload['customer']['name'] ?? '',
            '{{customer.email}}' => $payload['customer']['email'] ?? '',
            '{{device.fingerprint}}' => $payload['device']['fingerprint'] ?? '',
            '{{device.platform}}' => $payload['device']['platform'] ?? '',
            '{{raw_payload}}' => json_encode($payload),
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    /**
     * 测试单个条件
     */
    public function testCondition(array $condition, array $testPayload, string $eventType): array
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? '';

        $actualValue = $field === 'event_type' ? $eventType : $this->getFieldValue($testPayload, $field);
        $matched = $this->evaluateCondition($actualValue, $operator, $expectedValue);

        return [
            'field' => $field,
            'operator' => $operator,
            'expected' => $expectedValue,
            'actual' => $actualValue,
            'matched' => $matched,
        ];
    }

    /**
     * 批量测试一组过滤器或条件
     */
    public function batchTest(int $endpointId, array $testEvents): array
    {
        $filters = WebhookFilter::where('webhook_endpoint_id', $endpointId)
            ->active()
            ->byPriority()
            ->get();

        $results = [];
        foreach ($testEvents as $event) {
            $eventType = $event['event_type'] ?? 'unknown';
            $payload = $event['payload'] ?? [];

            $matchedFilters = [];
            foreach ($filters as $filter) {
                $isMatch = $this->matches($filter, $eventType, $payload);
                $transformed = $isMatch && $filter->payload_template
                    ? $this->applyTemplate($filter->payload_template, $payload, $eventType)
                    : null;

                $matchedFilters[] = [
                    'filter_id' => $filter->id,
                    'filter_name' => $filter->name,
                    'matched' => $isMatch,
                    'transformed_payload' => $transformed,
                ];
            }

            $results[] = [
                'event_type' => $eventType,
                'matched_filters' => $matchedFilters,
                'any_matched' => collect($matchedFilters)->contains('matched', true),
            ];
        }

        return $results;
    }

    /**
     * 获取端点支持的筛选选项 (供前端下拉使用)
     */
    public function getFilterOptions(): array
    {
        return [
            'fields' => config('webhook-filter.supported_fields', []),
            'operators' => config('webhook-filter.operators', []),
            'template_variables' => config('webhook-filter.template_variables', []),
            'match_types' => [
                ['value' => 'all', 'label' => '全部匹配 (AND)'],
                ['value' => 'any', 'label' => '任一匹配 (OR)'],
            ],
            'event_types' => [
                'license.activated',
                'license.deactivated',
                'license.expired',
                'license.revoked',
                'license.suspended',
                'license.restored',
                'license.updated',
                'device.limit_reached',
                'customer.created',
                'customer.updated',
                'subscription.created',
                'subscription.updated',
                'subscription.cancelled',
                'subscription.renewed',
                'trial.converted',
                'payment.success',
                'payment.failed',
            ],
        ];
    }
}
