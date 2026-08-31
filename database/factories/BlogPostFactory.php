<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(3),
            'type' => fake()->randomElement(['blog', 'changelog', 'release_note']),
            'content' => fake()->paragraphs(3, true),
            'excerpt' => fake()->sentence(10),
            'is_published' => true,
            'is_featured' => false,
            'author' => fake()->name(),
            'author_id' => User::factory(),
            'published_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function changelog(): static
    {
        return $this->state(fn(array $attrs) => [
            'type' => 'changelog',
            'title' => 'v' . fake()->semver() . ' 更新日志',
        ]);
    }
}
