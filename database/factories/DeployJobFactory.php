<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeployJobFactory extends Factory
{
    protected $model = \App\Models\DeployJob::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'deploy_environment_id' => function (array $attributes) {
                return \App\Models\DeployEnvironment::factory()->create([
                    'tenant_id' => $attributes['tenant_id'],
                ])->id;
            },
            'deploy_release_id' => function (array $attributes) {
                return \App\Models\DeployRelease::factory()->create([
                    'tenant_id' => $attributes['tenant_id'],
                ])->id;
            },
            'type' => 'full',
            'status' => 'pending',
            'steps' => [
                ['name' => '拉取代码', 'status' => 'success', 'duration_ms' => 1200],
                ['name' => '安装依赖', 'status' => 'success', 'duration_ms' => 3400],
                ['name' => '构建', 'status' => 'success', 'duration_ms' => 5600],
                ['name' => '部署', 'status' => 'pending', 'duration_ms' => 0],
            ],
            'triggered_by' => '测试用户',
        ];
    }
}
