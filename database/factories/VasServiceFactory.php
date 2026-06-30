<?php

namespace Database\Factories;

use App\Models\VasService;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class VasServiceFactory extends Factory
{
    protected $model = VasService::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(['feature', 'support', 'storage', 'api', 'ai']);
        return [
            'tenant_id' => Tenant::factory(),
            'code' => $this->faker->unique()->word() . '_vas',
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category' => $category,
            'price_monthly' => $this->faker->randomFloat(2, 10, 500),
            'price_yearly' => $this->faker->randomFloat(2, 100, 5000),
            'currency' => 'CNY',
            'billing_mode' => 'flat',
            'features' => ['功能A', '功能B', '功能C'],
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
