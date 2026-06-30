<?php

namespace Database\Factories;

use App\Models\SeoMetadata;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeoMetadataFactory extends Factory
{
    protected $model = SeoMetadata::class;

    public function definition(): array
    {
        return [
            'seoable_type' => 'App\\Models\\Page',
            'seoable_id' => 1,
            'tenant_id' => Tenant::factory(),
            'meta_title' => $this->faker->sentence(4),
            'meta_description' => $this->faker->paragraph(1),
            'meta_keywords' => implode(',', $this->faker->words(5)),
            'robots' => 'index,follow',
            'priority' => '0.5',
            'change_frequency' => 'monthly',
        ];
    }
}
