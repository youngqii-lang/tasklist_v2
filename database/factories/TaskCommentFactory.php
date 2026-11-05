<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
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
            'author_id' => Account::factory(),
            'content' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the comment replies to the given comment on the same task.
     */
    public function replyTo(TaskComment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $parent->task_id,
            'parent_comment_id' => $parent->id,
        ]);
    }
}
