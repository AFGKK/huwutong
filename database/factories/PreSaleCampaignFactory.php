<?php

namespace Database\Factories;

use App\Models\PreSaleCampaign;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreSaleCampaignFactory extends Factory
{
    protected $model = PreSaleCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => 'pre_sale',
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->paragraph(),
            'product_id' => Product::factory(),
            'target_amount' => $this->faker->randomFloat(2, 10000, 100000),
            'min_amount' => $this->faker->randomFloat(2, 1000, 5000),
            'raised_amount' => 0,
            'target_backers' => $this->faker->numberBetween(50, 500),
            'current_backers' => 0,
            'deposit_rate' => $this->faker->randomFloat(2, 10, 50),
            'deposit_amount' => 0,
            'currency' => 'CNY',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
            'estimated_delivery_at' => now()->addDays(60),
            'status' => 'draft',
            'tiers' => null,
            'settings' => null,
        ];
    }

    public function preSale(): static
    {
        return $this->state(fn() => ['type' => 'pre_sale']);
    }

    public function crowdfunding(): static
    {
        return $this->state(fn() => ['type' => 'crowdfunding']);
    }

    public function active(): static
    {
        return $this->state(fn() => [
            'status' => 'active',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
        ]);
    }

    public function success(): static
    {
        return $this->state(fn() => [
            'status' => 'success',
            'raised_amount' => $this->faker->randomFloat(2, 50000, 150000),
            'current_backers' => $this->faker->numberBetween(100, 500),
        ]);
    }
}
