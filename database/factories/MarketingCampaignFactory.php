<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = \App\Models\MarketingCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => '测试营销活动',
            'slug' => 'test-campaign',
            'status' => 'draft',
            'type' => 'email',
            'audience_type' => 'all',
            'created_by' => 1,
        ];
    }
}
