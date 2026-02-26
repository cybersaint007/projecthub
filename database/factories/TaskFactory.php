<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'epic_id' => Epic::factory(),
            'position' => 10,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => 'Backlog',
            'agent' => 'human',
            'priority' => 3,
        ];
    }
}
