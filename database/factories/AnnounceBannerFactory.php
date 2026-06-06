<?php

namespace Database\Factories;

use App\Models\AnnounceBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnounceBannerFactory extends Factory
{
    protected $model = AnnounceBanner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'type' => fake()->randomElement(['info', 'success', 'warning', 'danger']),
            'position' => fake()->randomElement(['top', 'bottom']),
            'can_close' => true,
            'link_url' => null,
            'link_text' => null,
            'roles' => null,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_by' => null,
        ];
    }
}
