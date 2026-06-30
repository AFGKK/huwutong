<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        static $keys = ['theme', 'language', 'notification_freq', 'dashboard_widgets', 'content_focus'];
        static $index = 0;

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => 1,
            'customer_id' => null,
            'preference_key' => $keys[$index++ % count($keys)],
            'preference_value' => $this->faker->word(),
            'preference_type' => 'string',
        ];
    }
}
