<?php

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateCreative;
use Illuminate\Database\Eloquent\Factories\Factory;

class AffiliateCreativeFactory extends Factory
{
    protected $model = AffiliateCreative::class;

    public function definition(): array
    {
        return [
            'campaign_id' => AffiliateCampaign::factory(),
            'type' => $this->faker->randomElement(['banner', 'landing_page', 'link', 'coupon']),
            'name' => $this->faker->sentence(2),
            'url' => $this->faker->url(),
            'content' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }
}
