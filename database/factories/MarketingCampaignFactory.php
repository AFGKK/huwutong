<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = \App\Models\MarketingCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => '测试营销活动',
            'slug' => 'test-campaign',
            'status' => 'draft',
            'type' => 'email',
            'audience_type' => 'all',
            'created_by' => User::factory(),
        ];
    }
}
