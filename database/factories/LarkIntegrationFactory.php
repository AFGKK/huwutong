<?php

namespace Database\Factories;

use App\Models\LarkIntegration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LarkIntegrationFactory extends Factory
{
    protected $model = LarkIntegration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => '飞书集成',
            'is_enabled' => false,
            'app_id' => null,
            'app_secret' => null,
            'bot_webhook_url' => null,
            'notify_enabled' => true,
        ];
    }

    public function configured(): static
    {
        return $this->state(fn(array $attrs) => [
            'app_id' => 'cli_' . fake()->lexify('????????'),
            'app_secret' => encrypt('secret_' . fake()->lexify('??????????')),
            'is_enabled' => true,
        ]);
    }
}
