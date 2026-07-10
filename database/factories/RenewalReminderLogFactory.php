<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RenewalReminderLogFactory extends Factory
{
    protected $model = \App\Models\RenewalReminderLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => \App\Models\Subscription::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'channel' => 'mail',
            'template_name' => '续费提醒模板',
            'subject' => '续费提醒',
            'status' => 'pending',
        ];
    }
}
