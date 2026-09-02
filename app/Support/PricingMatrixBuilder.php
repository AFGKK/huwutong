<?php

namespace App\Support;

/**
 * 从公开套餐 limits / metadata.comparison 构建定价对比矩阵行。
 */
class PricingMatrixBuilder
{
    /**
     * @param  list<array<string, mixed>>  $plans
     * @return list<array{label: string, tip: string, cells: list<string>}>
     */
    public function build(array $plans): array
    {
        $definitions = config('pricing-matrix.rows', []);
        if (! is_array($definitions)) {
            return [];
        }

        $labels = __('pricing_matrix');
        if (! is_array($labels)) {
            $labels = [];
        }

        $rows = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = $definition['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $meta = $labels[$key] ?? null;
            if (! is_array($meta)) {
                continue;
            }

            $cells = [];
            foreach ($plans as $plan) {
                if (! is_array($plan)) {
                    $cells[] = $this->emptyCell();

                    continue;
                }

                $raw = $this->resolveRawValue($plan, $definition);
                $cells[] = $this->formatValue($raw, $definition, $labels);
            }

            $rows[] = [
                'label' => (string) ($meta['label'] ?? $key),
                'tip' => (string) ($meta['tip'] ?? ''),
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $definition
     */
    private function resolveRawValue(array $plan, array $definition): mixed
    {
        $source = $definition['source'] ?? 'limits';
        $key = $definition['key'] ?? '';

        if ($source === 'comparison') {
            $comparison = $plan['metadata']['comparison'] ?? [];

            return is_array($comparison) ? ($comparison[$key] ?? null) : null;
        }

        if ($source === 'plan') {
            $field = $definition['field'] ?? $key;

            return $plan[$field] ?? null;
        }

        $limits = $plan['limits'] ?? [];

        return is_array($limits) ? ($limits[$key] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $labels
     */
    private function formatValue(mixed $raw, array $definition, array $labels): string
    {
        $type = $definition['type'] ?? 'text';
        $units = is_array($labels['units'] ?? null) ? $labels['units'] : [];
        $values = is_array($labels['values'] ?? null) ? $labels['values'] : [];

        if ($raw === null || $raw === '' || $raw === false) {
            return $this->emptyCell();
        }

        return match ($type) {
            'boolean' => $this->truthy($raw) ? $this->checkMark($labels) : $this->emptyCell(),
            'count' => $this->formatCount($raw, $definition['unit'] ?? null, $units, $labels),
            'rate' => $this->formatRate($raw, $labels),
            'enum' => $this->formatEnum($raw, $definition['enum_prefix'] ?? '', $values),
            default => (string) $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $units
     * @param  array<string, mixed>  $labels
     */
    private function formatCount(mixed $raw, ?string $unitKey, array $units, array $labels): string
    {
        if (! is_numeric($raw)) {
            return $this->emptyCell();
        }

        $number = (int) $raw;

        if ($number < 0) {
            return $this->unlimitedLabel($labels);
        }

        $formatted = number_format($number);
        $unit = is_string($unitKey) && isset($units[$unitKey]) ? (string) $units[$unitKey] : '';

        return $unit !== '' ? $formatted.' '.$unit : $formatted;
    }

    /**
     * @param  array<string, mixed>  $labels
     */
    private function formatRate(mixed $raw, array $labels): string
    {
        if (! is_numeric($raw)) {
            return $this->emptyCell();
        }

        $suffix = (string) ($labels['per_minute'] ?? '/min');

        return number_format((int) $raw).$suffix;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function formatEnum(mixed $raw, string $prefix, array $values): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return $this->emptyCell();
        }

        $key = (string) $raw;
        $group = is_array($values[$prefix] ?? null) ? $values[$prefix] : [];
        if (isset($group[$key])) {
            return (string) $group[$key];
        }

        if (is_numeric($raw)) {
            return (string) $raw;
        }

        return $this->emptyCell();
    }

    private function truthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', 'yes', 'y', 'on'], true);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $labels
     */
    private function unlimitedLabel(array $labels): string
    {
        return (string) ($labels['unlimited'] ?? 'Unlimited');
    }

    /**
     * @param  array<string, mixed>  $labels
     */
    private function checkMark(array $labels): string
    {
        return (string) ($labels['check'] ?? '✓');
    }

    private function emptyCell(): string
    {
        return '—';
    }
}
