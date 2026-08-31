<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseContract;
use App\Models\LicenseContractAssignment;
use App\Models\LicenseContractEvaluationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 智能合约式授权条件评估引擎
 *
 * 评估合约中的条件列表，判断是否满足授权要求。
 * 支持多种条件类型和评估模式(all/any/custom)。
 */
class ContractConditionEngine
{
    private static function operators(): array { return [
        'eq' => __('app.contract_condition.contract_condition_4c35bf2e48'),
        'neq', 'ne' => __('app.contract_condition.contract_condition_14a8af58ec'),
        'gt' => __('app.contract_condition.contract_condition_2791dca39c'),
        'gte', 'ge' => __('app.contract_condition.contract_condition_a1d1e58294'),
        'lt' => __('app.contract_condition.contract_condition_f09dc0d1d6'),
        'lte', 'le' => __('app.contract_condition.contract_condition_1ec4aae048'),
        'in' => __('app.contract_condition.contract_condition_02c1f70b74'),
        'not_in' => __('app.contract_condition.contract_condition_80a87de435'),
        'between' => __('app.contract_condition.contract_condition_43ef055625'),
        'contains' => __('app.contract_condition.contract_condition_e13556bb35'),
        'regex' => __('app.contract_condition.contract_condition_459947ebab'),
        'starts_with' => __('app.contract_condition.contract_condition_c2d8e69ec2'),
        'ends_with' => __('app.contract_condition.contract_condition_9460100bd1'),
    ]; }

    /**
     * 评估合约的全部条件
     *
     * @return array ['granted' => bool, 'conditions_results' => [...], 'reason' => string]
     */
    public function evaluate(LicenseContract $contract, array $context): array
    {
        $startTime = microtime(true);
        $results = [];
        $conditions = $contract->conditions ?? [];
        $mode = $contract->evaluation_mode ?? 'all';

        foreach ($conditions as $condition) {
            $conditionResult = $this->evaluateSingleCondition($condition, $context);
            $results[] = $conditionResult;
        }

        // 根据评估模式判断最终结果
        $granted = $this->resolveEvaluationMode($mode, $results, $contract);

        $conditionResults = $granted
            ? ['granted' => true, 'conditions_results' => $results]
            : [
                'granted' => false,
                'conditions_results' => $results,
                'failed_conditions' => array_values(array_filter($results, fn($r) => !$r['matched'])),
            ];

        $conditionResults['evaluation_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $conditionResults;
    }

    /**
     * 评估单个条件
     */
    protected function evaluateSingleCondition(array $condition, array $context): array
    {
        $type = $condition['type'] ?? 'custom_field';
        $operator = $condition['operator'] ?? 'eq';
        $field = $condition['field'] ?? null;
        $value = $condition['value'] ?? null;
        $valueSource = $condition['value_source'] ?? null;

        // 获取上下文值
        $contextValue = $this->resolveContextValue($field, $context, $type);

        // 如果定义了value_source，从上下文中获取对比值
        if ($valueSource) {
            $value = $this->resolveContextValue($valueSource, $context, $type);
        }

        // 类型特定的评估
        $matched = $this->evaluateByType($type, $operator, $contextValue, $value, $condition, $context);

        return [
            'type' => $type,
            'field' => $field,
            'operator' => $operator,
            'expected' => $value,
            'actual' => $contextValue,
            'matched' => $matched,
            'label' => $condition['label'] ?? $type,
        ];
    }

    /**
     * 按条件类型评估
     */
    protected function evaluateByType(string $type, string $operator, mixed $contextValue, mixed $value, array $condition, array $context): bool
    {
        return match ($type) {
            'time_window' => $this->evaluateTimeWindow($operator, $condition, $context),
            'ip_range' => $this->evaluateIpRange($contextValue, $value),
            'geo_location' => $this->evaluateGeoLocation($contextValue, $value, $operator),
            'device_count', 'concurrent_users' => $this->evaluateNumericComparison($contextValue, $value, $operator),
            'user_role' => $this->evaluateUserRole($contextValue, $value, $operator, $context),
            'license_status', 'license_type', 'subscription_plan' => $this->evaluateStringComparison($contextValue, $value, $operator),
            'feature_enabled' => $this->evaluateFeatureEnabled($contextValue, $value),
            'rate_limit' => $this->evaluateRateLimit($contextValue, $value, $operator, $condition),
            default => $this->evaluateGenericOperator($contextValue, $value, $operator),
        };
    }

    /**
     * 时段窗口评估
     */
    protected function evaluateTimeWindow(string $operator, array $condition, array $context): bool
    {
        $days = $condition['days'] ?? [1, 2, 3, 4, 5];
        $startTime = $condition['start_time'] ?? '00:00';
        $endTime = $condition['end_time'] ?? '23:59';
        $timezone = $condition['timezone'] ?? 'UTC';

        $now = now()->setTimezone($timezone);
        $currentDayOfWeek = isset($context['current_day'])
            ? (int) $context['current_day']
            : (int) $now->format('N'); // 1=Mon, 7=Sun
        $currentTime = $context['current_time'] ?? $now->format('H:i');

        // 检查星期
        if (!in_array($currentDayOfWeek, $days, true)) {
            return in_array($operator, ['not_in', 'neq'], true);
        }

        $inWindow = $currentTime >= $startTime && $currentTime <= $endTime;

        return match ($operator) {
            'in', 'eq', 'between' => $inWindow,
            'not_in', 'neq' => !$inWindow,
            default => $this->evaluateGenericOperator($currentTime, [$startTime, $endTime], $operator),
        };
    }

    /**
     * IP 范围评估
     */
    protected function evaluateIpRange(?string $ip, mixed $cidrList): bool
    {
        if (!$ip || !$cidrList) return false;
        if (!is_array($cidrList)) $cidrList = [$cidrList];

        foreach ($cidrList as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) return true;
        }
        return false;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        $rangeIp = $parts[0];
        $prefix = isset($parts[1]) ? (int)$parts[1] : 32;

        $ipLong = ip2long($ip);
        $rangeLong = ip2long($rangeIp);
        if ($ipLong === false || $rangeLong === false) return false;

        $mask = -1 << (32 - $prefix);
        return ($ipLong & $mask) === ($rangeLong & $mask);
    }

    /**
     * 地理位置评估
     */
    protected function evaluateGeoLocation(?string $country, mixed $allowedCountries, string $operator): bool
    {
        if (!$country) return false;
        if (!is_array($allowedCountries)) $allowedCountries = [$allowedCountries];

        $inList = in_array($country, $allowedCountries);
        return $operator === 'not_in' ? !$inList : $inList;
    }

    /**
     * 数值比较
     */
    protected function evaluateNumericComparison(mixed $contextValue, mixed $threshold, string $operator): bool
    {
        $contextValue = (int)$contextValue;
        $threshold = (int)$threshold;

        return match ($operator) {
            'eq' => $contextValue === $threshold,
            'neq', 'ne' => $contextValue !== $threshold,
            'gt' => $contextValue > $threshold,
            'gte', 'ge' => $contextValue >= $threshold,
            'lt' => $contextValue < $threshold,
            'lte', 'le' => $contextValue <= $threshold,
            default => $contextValue === $threshold,
        };
    }

    /**
     * 字符串比较（角色、状态等）
     */
    protected function evaluateStringComparison(mixed $contextValue, mixed $expected, string $operator): bool
    {
        if ($operator === 'in' || $operator === 'not_in') {
            $expected = is_array($expected) ? $expected : [$expected];
            $inList = in_array($contextValue, $expected);
            return $operator === 'not_in' ? !$inList : $inList;
        }

        return $this->evaluateGenericOperator((string)$contextValue, (string)$expected, $operator);
    }

    /**
     * 用户角色评估
     */
    protected function evaluateUserRole(mixed $userRoles, mixed $requiredRoles, string $operator, array $context): bool
    {
        if (!$userRoles) return false;
        $userRoles = is_array($userRoles) ? $userRoles : [$userRoles];
        $requiredRoles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];

        $hasRole = !empty(array_intersect($userRoles, $requiredRoles));
        return $operator === 'not_in' ? !$hasRole : $hasRole;
    }

    /**
     * 功能启用评估
     */
    protected function evaluateFeatureEnabled(?bool $enabled, mixed $expected): bool
    {
        $expected = filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        return $enabled === $expected;
    }

    /**
     * 速率限制评估
     */
    protected function evaluateRateLimit(mixed $currentCount, mixed $limit, string $operator, array $condition): bool
    {
        $limit = (int)($limit ?? $condition['max_count'] ?? 0);
        $currentCount = (int)$currentCount;
        return $currentCount <= $limit;
    }

    /**
     * 通用运算符评估
     */
    protected function evaluateGenericOperator(mixed $contextValue, mixed $value, string $operator): bool
    {
        return match ($operator) {
            'eq' => $contextValue == $value,
            'neq', 'ne' => $contextValue != $value,
            'gt' => $contextValue > $value,
            'gte', 'ge' => $contextValue >= $value,
            'lt' => $contextValue < $value,
            'lte', 'le' => $contextValue <= $value,
            'in' => is_array($value) && in_array($contextValue, $value),
            'not_in' => is_array($value) && !in_array($contextValue, $value),
            'between' => is_array($value) && count($value) === 2
                && $contextValue >= $value[0] && $contextValue <= $value[1],
            'contains' => is_string($contextValue) && is_string($value)
                && str_contains($contextValue, $value),
            'regex' => is_string($contextValue) && is_string($value)
                && preg_match($value, $contextValue),
            'starts_with' => is_string($contextValue) && is_string($value)
                && str_starts_with($contextValue, $value),
            'ends_with' => is_string($contextValue) && is_string($value)
                && str_ends_with($contextValue, $value),
            default => $contextValue == $value,
        };
    }

    /**
     * 从上下文解析字段值
     */
    protected function resolveContextValue(?string $field, array $context, string $type): mixed
    {
        if (!$field) {
            return $this->getDefaultTypeValue($type);
        }

        // 点号路径解析: license.max_devices, user.roles
        $keys = explode('.', $field);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->$key)) {
                $value = $value->$key;
            } else {
                return $this->getDefaultTypeValue($type);
            }
        }

        return $value;
    }

    /**
     * 获取条件类型的默认值
     */
    protected function getDefaultTypeValue(string $type): mixed
    {
        return match ($type) {
            'device_count', 'concurrent_users', 'rate_limit' => 0,
            'time_window' => now()->format('H:i'),
            'ip_range' => request()->ip(),
            'geo_location' => 'unknown',
            'user_role' => [],
            'license_status', 'license_type', 'subscription_plan' => '',
            'feature_enabled' => false,
            default => null,
        };
    }

    /**
     * 根据评估模式解析最终结果
     */
    protected function resolveEvaluationMode(string $mode, array $results, LicenseContract $contract): bool
    {
        return match ($mode) {
            'any' => !empty(array_filter($results, fn($r) => $r['matched'])),
            'custom' => $this->evaluateCustomExpression($contract->custom_expression ?? '', $results),
            default => !empty($results) && count(array_filter($results, fn($r) => $r['matched'])) === count($results),
        };
    }

    /**
     * 评估自定义表达式（简单AND/OR组合）
     */
    protected function evaluateCustomExpression(string $expression, array $results): bool
    {
        if (empty($expression)) {
            return !empty($results) && count(array_filter($results, fn($r) => $r['matched'])) === count($results);
        }

        $indexed = [];
        foreach ($results as $i => $r) {
            $indexed["cond_{$i}"] = $r['matched'];
        }

        try {
            $expression = str_replace(
                array_keys($indexed),
                array_map(fn($v) => $v ? 'true' : 'false', $indexed),
                $expression
            );

            return eval("return {$expression};");
        } catch (\Throwable $e) {
            Log::warning('[ContractCondition] 自定义表达式评估失败', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
