<?php

namespace Database\Factories;

use App\Models\InvoiceLineItem;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineItemFactory extends Factory
{
    protected $model = InvoiceLineItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => 'subscription',
            'description' => $this->faker->sentence(3),
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 50, 500),
            'amount' => 0, // will be calculated
            'currency' => 'CNY',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InvoiceLineItem $item) {
            if ($item->amount == 0 && $item->unit_price > 0) {
                $item->amount = $item->quantity * $item->unit_price;
            }
        });
    }
}
