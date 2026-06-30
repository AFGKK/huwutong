<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\WorkflowDesign;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowDesignFactory extends Factory
{
    protected $model = WorkflowDesign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['general', 'approval', 'license']),
            'is_active' => true,
            'status' => 'draft',
        ];
    }
}
