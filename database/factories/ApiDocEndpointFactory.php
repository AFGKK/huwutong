<?php

namespace Database\Factories;

use App\Models\ApiDocEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiDocEndpointFactory extends Factory
{
    protected $model = ApiDocEndpoint::class;

    public function definition(): array
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $groups = ['licenses', 'subscriptions', 'invoices', 'customers', 'admin'];
        $paths = [
            '/api/admin/licenses',
            '/api/admin/licenses/{id}',
            '/api/admin/subscriptions',
            '/api/admin/invoices',
            '/api/admin/customers',
            '/api/admin/users',
            '/api/admin/settings',
        ];

        return [
            'method' => $this->faker->randomElement($methods),
            'path' => $this->faker->randomElement($paths),
            'summary' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph,
            'group' => $this->faker->randomElement($groups),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
