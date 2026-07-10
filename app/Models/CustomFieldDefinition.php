<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCustomFieldDefinition
 */
class CustomFieldDefinition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'field_type', 'options',
        'placeholder', 'description', 'is_required', 'is_active',
        'applies_to', 'sort_order', 'group', 'default_value',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'applies_to' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'field_definition_id');
    }

    /**
     * 获取自定义字段值（新多态表）
     */
    public function polymorphicValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'field_definition_id');
    }

    /**
     * 获取（旧表）License 自定义字段值
     */
    public function licenseValues(): HasMany
    {
        return $this->hasMany(LicenseCustomFieldValue::class, 'field_definition_id');
    }

    /**
     * 获取全局+租户字段定义
     */
    public static function getForTenant(?int $tenantId, ?string $appliesTo = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($appliesTo) {
            $query->where(function ($q) use ($appliesTo) {
                $q->whereNull('applies_to')
                  ->orWhereJsonContains('applies_to', $appliesTo);
            });
        }

        return $query->get();
    }

    /**
     * 为指定实体获取字段定义及其值
     */
    public static function getForEntity(
        Model $entity,
        ?int $tenantId = null,
    ): \Illuminate\Database\Eloquent\Collection {
        $entityType = self::entityTypeFromClass(get_class($entity));
        $tenantId = $tenantId ?? ($entity->tenant_id ?? null);

        $definitions = self::getForTenant($tenantId, $entityType);
        $values = CustomFieldValue::where('fieldable_id', $entity->getKey())
            ->where('fieldable_type', $entity->getMorphClass())
            ->get()
            ->keyBy('field_definition_id');

        return $definitions->map(function ($def) use ($values) {
            $val = $values->get($def->id);
            $def->setRelation('currentValue', $val);
            $def->value = $val?->value ?? $def->default_value;
            $def->value_id = $val?->id;
            return $def;
        });
    }

    /**
     * 从类名转换为实体短名称
     */
    public static function entityTypeFromClass(string $class): string
    {
        $map = [
            'App\Models\License' => 'license',
            'App\Models\Customer' => 'customer',
            'App\Models\Product' => 'product',
        ];
        return $map[$class] ?? 'license';
    }
}
