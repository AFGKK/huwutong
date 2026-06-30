<?php

namespace Database\Factories;

use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayoutBatchFactory extends Factory
{
    protected $model = PayoutBatch::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(['bank', 'alipay', 'wechat', 'paypal']);
        $count = $this->faker->numberBetween(1, 20);
        $amount = $this->faker->randomFloat(2, 1000, 100000);

        return [
            'batch_no' => PayoutBatch::generateBatchNo(),
            'title' => $this->faker->sentence(3),
            'channel' => $channel,
            'total_count' => $count,
            'total_amount' => $amount,
            'total_fee' => round($amount * 0.01, 2),
            'status' => 'pending',
            'created_by' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
