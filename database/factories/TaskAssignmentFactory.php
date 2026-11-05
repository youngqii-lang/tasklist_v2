<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'account_id' => Account::factory()->employee(),
            'assigned_at' => now(),
        ];
    }

    /**
     * Indicate that the assignment has ended.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'unassigned_at' => now(),
        ]);
    }
}
