<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 自定义字段服务（M3-46）
 *
 * 支持多态实体（License/Customer/Product）的自定义字段管理：
 * - 字段定义 CRUD（支持 applies_to 筛选目标实体）
 * - 字段值读写（支持批量更新）
 * - 字段值校验（按字段类型验证）
 * - 字段值查询（按字段筛选实体）
 */
class CustomFieldService
{
    /**
     * 支持的字段类型
     */
    const FIELD_TYPES = [
        'text', 'textarea', 'number', 'select',
        'multi_select', 'date', 'boolean', 'url', 'email', 'color',
    ];

    /**
     * 支持的目标实体
     */
    const ENTITY_TYPES = ['license', 'customer', 'product'];

    /**
     * 获取实体类型的 Morph Class
     */
    public function morphClassFor(string $entityType): string
    {
        $map = [
            'license' => 'App\Models\License',
            'customer' => 'App\Models\Customer',
            'product' => 'App\Models\Product',
        ];
        return $map[$entityType] ?? 'App\Models\License';
    }

    // ═══════════════════════════════════════════
    // 字段定义管理
    // ═══════════════════════════════════════════

    /**
     * 创建字段定义
     */
    public function createDefinition(array $data, ?int $tenantId = null): CustomFieldDefinition
    {
        // 重名校验
        $slug = Str::slug($data['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (CustomFieldDefinition::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // 校验 applies_to
        $appliesTo = $data['applies_to'] ?? ['license'];
        if (is_string($appliesTo)) {
            $appliesTo = explode(',', $appliesTo);
        }
        $appliesTo = array_intersect($appliesTo, self::ENTITY_TYPES);

        return CustomFieldDefinition::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'field_type' => $data['field_type'],
            'options' => in_array($data['field_type'], ['select', 'multi_select']) ? ($data['options'] ?? []) : null,
            'placeholder' => $data['placeholder'] ?? null,
            'description' => $data['description'] ?? null,
            'is_required' => $data['is_required'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'applies_to' => $appliesTo,
            'sort_order' => $data['sort_order'] ?? 0,
            'group' => $data['group'] ?? null,
            'default_value' => $data['default_value'] ?? null,
        ]);
    }

    /**
     * 更新字段定义
     */
    public function updateDefinition(CustomFieldDefinition $field, array $data): CustomFieldDefinition
    {
        if (isset($data['name']) && $data['name'] !== $field->name) {
            $slug = Str::slug($data['name']);
            $baseSlug = $slug;
            $counter = 1;
            while (CustomFieldDefinition::where('slug', $slug)->where('id', '!=', $field->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $data['slug'] = $slug;
        }

        if (isset($data['applies_to'])) {
            $appliesTo = is_string($data['applies_to']) ? explode(',', $data['applies_to']) : $data['applies_to'];
            $data['applies_to'] = array_intersect($appliesTo, self::ENTITY_TYPES);
        }

        if (isset($data['field_type']) && in_array($data['field_type'], ['select', 'multi_select'])) {
            $data['options'] = $data['options'] ?? [];
        }

        $field->update($data);
        return $field->fresh();
    }

    /**
     * 删除字段定义（连带删除所有值）
     */
    public function deleteDefinition(CustomFieldDefinition $field): void
    {
        DB::transaction(function () use ($field) {
            $field->polymorphicValues()->delete();
            $field->licenseValues()->delete();
            $field->delete();
        });
    }

    // ═══════════════════════════════════════════
    // 字段值管理
    // ═══════════════════════════════════════════

    /**
     * 获取实体的自定义字段值
     */
    public function getValues(Model $entity, ?int $tenantId = null): \Illuminate\Support\Collection
    {
        return CustomFieldDefinition::getForEntity($entity, $tenantId);
    }

    /**
     * 批量更新实体的自定义字段值
     *
     * @param Model $entity 目标实体
     * @param array $values [field_definition_id => value] 格式
     * @param int|null $tenantId
     * @return \Illuminate\Support\Collection
     */
    public function updateValues(Model $entity, array $values, ?int $tenantId = null): \Illuminate\Support\Collection
    {
        $entityType = CustomFieldDefinition::entityTypeFromClass(get_class($entity));
        $definitions = CustomFieldDefinition::getForTenant(
            $tenantId ?? ($entity->tenant_id ?? null),
            $entityType
        );

        DB::transaction(function () use ($entity, $values, $definitions) {
            foreach ($definitions as $def) {
                if (!array_key_exists((string) $def->id, $values)) {
                    continue;
                }

                $newValue = $values[(string) $def->id];

                // 校验
                $this->validateFieldValue($def, $newValue);

                if ($newValue === '' || $newValue === null) {
                    CustomFieldValue::where('field_definition_id', $def->id)
                        ->where('fieldable_id', $entity->getKey())
                        ->where('fieldable_type', $entity->getMorphClass())
                        ->delete();
                } else {
                    CustomFieldValue::updateOrCreate(
                        [
                            'field_definition_id' => $def->id,
                            'fieldable_id' => $entity->getKey(),
                            'fieldable_type' => $entity->getMorphClass(),
                        ],
                        ['value' => $newValue]
                    );
                }
            }
        });

        return $this->getValues($entity, $tenantId);
    }

    // ═══════════════════════════════════════════
    // 字段值校验
    // ═══════════════════════════════════════════

    /**
     * 校验单个字段值
     */
    public function validateFieldValue(CustomFieldDefinition $def, mixed $value, bool $throw = true): bool
    {
        // 必填校验
        if ($def->is_required && ($value === null || $value === '')) {
            if ($throw) {
                throw ValidationException::withMessages([
                    $def->slug => ["字段 \"{$def->name}\" 是必填的"],
                ]);
            }
            return false;
        }

        if ($value === null || $value === '') {
            return true; // 非必填的空值视为合法
        }

        // 类型校验
        $isValid = match ($def->field_type) {
            'text', 'textarea', 'color' => is_string($value),
            'number' => is_numeric($value),
            'select' => in_array($value, $def->options ?? []),
            'multi_select' => $this->validateMultiSelect($value, $def->options ?? []),
            'date' => (bool) strtotime((string) $value),
            'boolean' => in_array((string) $value, ['1', '0', 'true', 'false', 'yes', 'no', 1, 0, true, false], true),
            'url' => filter_var((string) $value, FILTER_VALIDATE_URL) !== false,
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false,
            default => true,
        };

        if (!$isValid && $throw) {
            throw ValidationException::withMessages([
                $def->slug => ["字段 \"{$def->name}\" 的值无效 (类型: {$def->field_type})"],
            ]);
        }

        return $isValid;
    }

    /**
     * 校验多选字段
     */
    protected function validateMultiSelect(mixed $value, array $options): bool
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $v) {
            if (!in_array(trim((string) $v), $options, true)) {
                return false;
            }
        }
        return true;
    }

    // ═══════════════════════════════════════════
    // 查询 — 按自定义字段筛选实体
    // ═══════════════════════════════════════════

    /**
     * 按自定义字段值筛选实体 ID
     *
     * @param string $entityType 实体类型 (license/customer/product)
     * @param array $filters [slug => value, ...]
     * @return array 匹配的实体 ID 列表
     */
    public function filterEntitiesByFields(string $entityType, array $filters): array
    {
        if (empty($filters)) {
            return [];
        }

        $morphClass = $this->morphClassFor($entityType);

        $query = CustomFieldValue::query()
            ->where('fieldable_type', $morphClass);

        foreach ($filters as $slug => $value) {
            $query->whereHas('fieldDefinition', function ($q) use ($slug, $value) {
                $q->where('slug', $slug)
                  ->where('is_active', true);
            });

            if ($value !== null) {
                $query->where('value', $value);
            }
        }

        return $query->pluck('fieldable_id')->unique()->values()->toArray();
    }

    /**
     * 复制字段值（用于 License 转移等场景）
     */
    public function copyValues(Model $fromEntity, Model $toEntity): void
    {
        $values = CustomFieldValue::where('fieldable_id', $fromEntity->getKey())
            ->where('fieldable_type', $fromEntity->getMorphClass())
            ->get();

        foreach ($values as $value) {
            CustomFieldValue::updateOrCreate(
                [
                    'field_definition_id' => $value->field_definition_id,
                    'fieldable_id' => $toEntity->getKey(),
                    'fieldable_type' => $toEntity->getMorphClass(),
                ],
                ['value' => $value->value]
            );
        }
    }

    // ═══════════════════════════════════════════
    // 元数据
    // ═══════════════════════════════════════════

    /**
     * 获取字段类型选项
     */
    public function fieldTypeOptions(): array
    {
        $labels = [
            'text' => '单行文本',
            'textarea' => '多行文本',
            'number' => '数字',
            'select' => '下拉单选',
            'multi_select' => '下拉多选',
            'date' => '日期',
            'boolean' => '开关/布尔',
            'url' => 'URL 链接',
            'email' => '邮箱地址',
            'color' => '颜色',
        ];

        return collect(self::FIELD_TYPES)->map(fn($t) => [
            'value' => $t,
            'label' => $labels[$t] ?? $t,
        ])->values()->toArray();
    }

    /**
     * 获取实体类型选项
     */
    public function entityTypeOptions(): array
    {
        $labels = [
            'license' => 'License',
            'customer' => '客户',
            'product' => '产品',
        ];

        return collect(self::ENTITY_TYPES)->map(fn($t) => [
            'value' => $t,
            'label' => $labels[$t] ?? $t,
        ])->values()->toArray();
    }
}
