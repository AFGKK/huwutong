<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxComplianceDocumentFactory extends Factory
{
    protected $model = \App\Models\TaxComplianceDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'document_type' => 'correspondence',
            'country' => 'CN',
            'title' => '税务通信',
            'document_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }
}
