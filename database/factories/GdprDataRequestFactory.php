<?php

namespace Database\Factories;

use App\Models\DataProcessingAgreement;
use App\Models\GdprDataRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GdprDataRequestFactory extends Factory
{
    protected $model = GdprDataRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                GdprDataRequest::TYPE_ACCESS,
                GdprDataRequest::TYPE_EXPORT,
                GdprDataRequest::TYPE_PORTABILITY,
                GdprDataRequest::TYPE_ERASURE,
            ]),
            'status' => GdprDataRequest::STATUS_PENDING,
            'reason' => $this->faker->sentence(),
            'request_data' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GdprDataRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'output_file' => 'gdpr-exports/test-' . $this->faker->md5 . '.json',
            'file_size' => $this->faker->numberBetween(100, 10000),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GdprDataRequest::STATUS_FAILED,
        ]);
    }
}
