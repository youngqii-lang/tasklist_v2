<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReview>
 */
class TaskReviewFactory extends Factory
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
            'reviewer_id' => Account::factory()->manager(),
            'decision' => 'approved',
        ];
    }

    /**
     * Indicate that the reviewer asked for a revision.
     */
    public function revisionRequested(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => 'revision_requested',
            'note' => fake()->sentence(),
        ]);
    }
}
