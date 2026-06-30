<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HeatmapLayerFactory extends Factory
{
    protected $model = \App\Models\HeatmapLayer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(3),
            'data_source' => $this->faker->randomElement(['license_activations', 'product_usage', 'api_calls', 'revenue']),
            'type' => $this->faker->randomElement(['heatmap_scatter', 'country_choropleth', 'region_bubble']),
            'is_active' => true,
        ];
    }
}
