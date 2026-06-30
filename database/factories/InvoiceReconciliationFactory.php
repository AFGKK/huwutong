<?php

namespace Database\Factories;

use App\Models\InvoiceReconciliation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceReconciliationFactory extends Factory
{
    protected $model = InvoiceReconciliation::class;

    public function definition(): array
    {
        $invoiceAmount = $this->faker->randomFloat(2, 100, 1000);
        $actualAmount = $this->faker->randomFloat(2, 100, 1000);
        return [
            'tenant_id' => Tenant::factory(),
            'reconciliation_type' => 'auto',
            'status' => 'pending',
            'invoice_amount' => $invoiceAmount,
            'actual_amount' => $actualAmount,
            'difference' => round($actualAmount - $invoiceAmount, 2),
            'currency' => 'CNY',
        ];
    }
}
