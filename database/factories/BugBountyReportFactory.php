<?php

namespace Database\Factories;

use App\Models\BugBountyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class BugBountyReportFactory extends Factory
{
    protected $model = BugBountyReport::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'steps_to_reproduce' => "1. " . fake()->sentence() . "\n2. " . fake()->sentence() . "\n3. " . fake()->sentence(),
            'impact' => fake()->paragraph(2),
            'severity' => fake()->randomElement(BugBountyReport::SEVERITIES),
            'vulnerability_type' => fake()->randomElement(['XSS', 'SQLI', 'CSRF', 'SSRF', 'RCE', 'IDOR', 'Broken Auth']),
            'status' => 'submitted',
            'reporter_name' => fake()->name(),
            'reporter_email' => fake()->safeEmail(),
            'reporter_handle' => fake()->userName(),
            'bounty_amount' => 0,
            'bounty_currency' => 'USD',
            'is_public' => false,
        ];
    }
}
