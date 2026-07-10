<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSandboxProduct
 */
class SandboxProduct extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'version', 'is_active', 'modules',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'modules' => 'array',
        ];
    }

    /**
     * 获取或创建默认沙箱产品
     */
    public static function getDefault(): self
    {
        $product = self::where('slug', 'sandbox-dev')->first();

        if (! $product) {
            $product = self::create([
                'name' => 'Sandbox Dev License',
                'slug' => 'sandbox-dev',
                'description' => '开发者沙箱测试产品，用于免费集成测试',
                'version' => '1.0.0',
                'is_active' => true,
                'modules' => ['activation', 'validation', 'device-binding', 'offline'],
            ]);
        }

        return $product;
    }
}
