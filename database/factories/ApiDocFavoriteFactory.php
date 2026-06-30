<?php

namespace Database\Factories;

use App\Models\ApiDocEndpoint;
use App\Models\ApiDocFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiDocFavoriteFactory extends Factory
{
    protected $model = ApiDocFavorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint_id' => ApiDocEndpoint::factory(),
            'note' => null,
        ];
    }
}
