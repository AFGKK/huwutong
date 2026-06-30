<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceSplit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceSplitFactory extends Factory
{
    protected $model = InvoiceSplit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'original_invoice_id' => Invoice::factory(),
            'split_invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 50, 500),
            'reason' => $this->faker->sentence(),
            'status' => 'completed',
        ];
    }
}
