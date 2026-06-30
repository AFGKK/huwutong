<?php

namespace Database\Factories;

use App\Models\CustomerFeedback;
use App\Models\FeatureVote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureVoteFactory extends Factory
{
    protected $model = FeatureVote::class;

    public function definition(): array
    {
        return [
            'feedback_id' => CustomerFeedback::factory(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'vote' => 1,
        ];
    }
}
