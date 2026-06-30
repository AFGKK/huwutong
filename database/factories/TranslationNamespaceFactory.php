<?php

namespace Database\Factories;

use App\Models\TranslationNamespace;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationNamespaceFactory extends Factory
{
    protected $model = TranslationNamespace::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'namespace' => $name,
            'label' => ucfirst($name),
            'description' => fake()->sentence(),
            'key_count' => 0,
        ];
    }
}
