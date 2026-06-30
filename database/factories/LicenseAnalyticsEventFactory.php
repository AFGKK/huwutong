<?php

namespace Database\Factories;

use App\Models\LicenseAnalyticsEvent;
use App\Models\License;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseAnalyticsEventFactory extends Factory
{
    protected $model = LicenseAnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'tenant_id' => Tenant::factory(),
            'event_type' => $this->faker->randomElement(['activation', 'heartbeat', 'validation', 'violation']),
            'ip_address' => $this->faker->ipv4(),
            'country_code' => $this->faker->randomElement(['CN', 'US', 'JP', 'DE', 'GB']),
            'country_name' => $this->faker->country(),
            'city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'platform' => $this->faker->randomElement(['windows', 'macos', 'linux', 'ios', 'android']),
            'sdk_version' => '1.0.0',
            'sdk_language' => 'php',
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
