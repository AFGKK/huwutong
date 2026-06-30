<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApmRequestFactory extends Factory
{
    private static array $commonPaths = [
        'api/licenses', 'api/products', 'api/customers',
        'api/devices', 'api/tickets', 'api/notifications',
        'api/billing/invoices', 'api/users/profile',
    ];

    private static array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function definition(): array
    {
        $isSlow = fake()->boolean(15);
        $method = fake()->randomElement(self::$methods);
        $path = fake()->randomElement(self::$commonPaths);
        $durationMs = $isSlow
            ? fake()->randomFloat(2, 1000, 8000)
            : fake()->randomFloat(2, 10, 950);

        $dbQueries = fake()->numberBetween(1, 20);
        $dbDurationMs = fake()->randomFloat(2, 1, $durationMs * 0.6);

        return [
            'method' => $method,
            'path' => $path,
            'route_name' => str_replace('/', '.', ltrim($path, '/')),
            'status_code' => fake()->randomElement([200, 200, 200, 201, 204, 301, 400, 401, 403, 404, 422, 500]),
            'duration_ms' => $durationMs,
            'db_duration_ms' => $dbDurationMs,
            'db_queries' => $dbQueries,
            'cache_duration_ms' => fake()->randomFloat(2, 0, 50),
            'cache_hits' => fake()->numberBetween(0, 10),
            'external_duration_ms' => fake()->randomFloat(2, 0, 500),
            'external_calls' => fake()->numberBetween(0, 5),
            'memory_mb' => fake()->randomFloat(2, 8, 256),
            'is_slow' => $isSlow,
            'slow_reason' => $isSlow ? "总耗时{$durationMs}ms" : null,
            'ip' => fake()->ipv4(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * 标记为慢请求
     */
    public function slow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_slow' => true,
            'duration_ms' => fake()->randomFloat(2, 1000, 8000),
            'slow_reason' => "总耗时{$attributes['duration_ms']}ms",
        ]);
    }

    /**
     * 指定 HTTP 方法
     */
    public function method(string $method): static
    {
        return $this->state(fn () => ['method' => strtoupper($method)]);
    }

    /**
     * 指定路径
     */
    public function path(string $path): static
    {
        return $this->state(fn () => [
            'path' => $path,
            'route_name' => str_replace('/', '.', ltrim($path, '/')),
        ]);
    }
}
