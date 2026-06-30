<?php

namespace Database\Factories;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateCreative;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AffiliateClickFactory extends Factory
{
    protected $model = \App\Models\AffiliateClick::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'campaign_id' => AffiliateCampaign::factory(),
            'referral_code' => strtoupper($this->faker->bothify('REF???###')),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referrer_url' => $this->faker->url(),
            'landing_url' => $this->faker->url(),
            'converted' => false,
            'commission_amount' => 0,
        ];
    }

    public function converted(): static
    {
        return $this->state(fn(array $attrs) => [
            'converted' => true,
            'converted_at' => now(),
            'commission_amount' => $this->faker->randomFloat(2, 50, 500),
        ]);
    }
}
