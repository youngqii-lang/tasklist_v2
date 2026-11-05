<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskEvidence>
 */
class TaskEvidenceFactory extends Factory
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
            'submitter_id' => Account::factory()->employee(),
            'type' => 'link',
            'url' => fake()->url(),
            'note' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the evidence is an uploaded file.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'url' => null,
            'file_path' => 'evidences/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1_000, 5_000_000),
        ]);
    }
}
