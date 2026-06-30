<?php

namespace Database\Factories;

use App\Models\UrlRedirect;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class UrlRedirectFactory extends Factory
{
    protected $model = UrlRedirect::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'source_url' => '/' . $this->faker->slug(3),
            'target_url' => '/' . $this->faker->slug(3),
            'status_code' => 301,
            'is_active' => true,
            'is_wildcard' => false,
            'hit_count' => $this->faker->numberBetween(0, 100),
        ];
    }
}
