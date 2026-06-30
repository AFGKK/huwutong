<?php

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AffiliateCampaignFactory extends Factory
{
    protected $model = AffiliateCampaign::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraph(),
            'status' => AffiliateCampaign::STATUS_DRAFT,
            'type' => AffiliateCampaign::TYPE_REFERRAL,
            'reward_first' => $this->faker->randomFloat(2, 10, 100),
            'budget_total' => $this->faker->randomFloat(2, 1000, 50000),
            'created_by' => User::factory(),
        ];
    }
}
