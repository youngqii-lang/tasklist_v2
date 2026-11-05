<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
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
            'account_id' => Account::factory(),
            'action' => 'status_changed',
            'changes' => ['status' => ['old' => 'todo', 'new' => 'in_progress']],
        ];
    }
}
