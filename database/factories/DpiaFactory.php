<?php

namespace Database\Factories;

use App\Models\Dpia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DpiaFactory extends Factory
{
    protected $model = Dpia::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'status' => Dpia::STATUS_DRAFT,
            'description' => $this->faker->paragraph(),
            'created_by' => User::factory(),
            'involved_data_categories' => ['用户账户信息', '设备指纹'],
            'stakeholders' => ['数据保护官', '法务'],
        ];
    }
}
