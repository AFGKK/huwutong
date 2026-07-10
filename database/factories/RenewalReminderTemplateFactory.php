<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RenewalReminderTemplateFactory extends Factory
{
    protected $model = \App\Models\RenewalReminderTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true) . '模板',
            'channel' => $this->faker->randomElement(['mail', 'sms', 'in_app']),
            'days_before' => $this->faker->randomElement([7, 14, 30]),
            'subject' => '续费提醒 - {{customer_name}}',
            'content' => '您好 {{customer_name}}，您的 {{plan}} 订阅将于 {{ends_at}} 到期，请及时续费。',
            'is_active' => true,
        ];
    }
}
