<?php

namespace Database\Factories;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceDeveloper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MarketplaceAppFactory extends Factory
{
    protected $model = MarketplaceApp::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'developer_id' => MarketplaceDeveloper::factory(),
            'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => ucfirst($name),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraphs(2, true),
            'category' => $this->faker->randomElement(['integration', 'automation', 'analytics', 'security', 'billing']),
            'status' => 'draft',
            'pricing_type' => 'free',
            'price' => 0,
            'permissions' => ['read:licenses', 'write:webhooks'],
            'current_version' => '1.0.0',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => ['status' => 'pending_review']);
    }
}
