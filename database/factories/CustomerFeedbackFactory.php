<?php

namespace Database\Factories;

use App\Models\CustomerFeedback;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFeedbackFactory extends Factory
{
    protected $model = CustomerFeedback::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['general', 'bug', 'feature_request', 'performance', 'ui_ux']),
            'subject' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(3),
            'rating' => $this->faker->optional(0.7)->numberBetween(1, 5),
            'status' => $this->faker->randomElement(['new', 'under_review', 'acknowledged', 'in_progress', 'resolved']),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
            'browser' => $this->faker->randomElement(['Chrome 120', 'Firefox 120', 'Safari 17']),
            'os' => $this->faker->randomElement(['Windows 10', 'macOS 14', 'Linux']),
            'screen_resolution' => $this->faker->randomElement(['1920x1080', '1440x900', '2560x1440']),
            'ip_address' => $this->faker->ipv4(),
            'page_url' => $this->faker->url(),
            'page_title' => $this->faker->sentence(3),
        ];
    }
}
