<?php

namespace Database\Factories;

use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceTemplateFactory extends Factory
{
    protected $model = InvoiceTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->word() . '_inv',
            'is_default' => false,
            'header' => ['company' => $this->faker->company(), 'address' => $this->faker->address()],
            'footer' => ['bank' => 'XX银行 6222 **** 1234', 'notes' => '感谢您的购买'],
            'color_scheme' => 'blue',
            'locale' => 'zh_CN',
            'currency' => 'CNY',
            'is_active' => true,
        ];
    }
}
