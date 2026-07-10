<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLlmProvider
 */
class LlmProvider extends Model
{
    protected $fillable = [
        'name', 'slug', 'driver', 'api_base', 'api_key',
        'models', 'default_model', 'config',
        'sort_order', 'is_active', 'is_fallback',
    ];

    protected function casts(): array
    {
        return [
            'models' => 'array',
            'config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
        ];
    }

    /**
     * API Key 应加密存储
     */
    public function getApiKey(): ?string
    {
        if (empty($this->api_key)) {
            return null;
        }

        $encrypted = $this->api_key;

        // 检查是否为加密值
        if (str_starts_with($encrypted, 'encrypted:')) {
            $payload = substr($encrypted, 10);
            try {
                return decrypt($payload);
            } catch (\Throwable) {
                return $encrypted;
            }
        }

        return $encrypted;
    }

    /**
     * 获取活跃的 Provider（按排序）
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * 获取备用 Provider
     */
    public static function getFallback(): ?self
    {
        return static::where('is_fallback', true)
            ->where('is_active', true)
            ->first();
    }
}
