<?php

namespace CleaniqueCoders\LaravelMcpKit\Database\Factories;

use CleaniqueCoders\LaravelMcpKit\Enums\TaskStatus;
use CleaniqueCoders\LaravelMcpKit\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            'assignee' => fake()->optional()->userName(),
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => TaskStatus::Open]);
    }

    public function done(): static
    {
        return $this->state(['status' => TaskStatus::Done]);
    }
}
