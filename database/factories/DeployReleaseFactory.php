<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DeployReleaseFactory extends Factory
{
    protected $model = \App\Models\DeployRelease::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'version' => $this->faker->semver(),
            'code_name' => $this->faker->words(3, true),
            'changelog' => $this->faker->paragraph(),
            'git_branch' => 'main',
            'git_commit_hash' => $this->faker->sha1(),
            'author' => $this->faker->name(),
            'status' => 'pending',
        ];
    }
}
