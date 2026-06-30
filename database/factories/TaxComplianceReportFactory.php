<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaxComplianceReportFactory extends Factory
{
    protected $model = \App\Models\TaxComplianceReport::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'report_type' => 'vat_return',
            'status' => 'draft',
            'country' => 'CN',
            'period' => '2026-06',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'total_sales' => 10000,
            'total_tax_collected' => 1300,
            'total_tax_payable' => 1300,
        ];
    }
}
