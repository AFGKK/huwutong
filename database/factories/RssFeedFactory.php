<?php

namespace Database\Factories;

use App\Models\RssFeed;
use Illuminate\Database\Eloquent\Factories\Factory;

class RssFeedFactory extends Factory
{
    protected $model = RssFeed::class;

    public function definition(): array
    {
        return [
            'feed_type' => fake()->randomElement(['blog', 'changelog', 'all']),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(10),
            'language' => 'zh-CN',
            'ttl' => '60',
        ];
    }
}
