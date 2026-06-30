<?php

namespace Database\Factories;

use App\Models\ApiDocSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiDocSchemaFactory extends Factory
{
    protected $model = ApiDocSchema::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word() . 'Schema',
            'type' => 'object',
            'description' => $this->faker->sentence,
            'schema' => ['type' => 'object', 'properties' => []],
            'properties' => ['id' => ['type' => 'integer']],
        ];
    }
}
