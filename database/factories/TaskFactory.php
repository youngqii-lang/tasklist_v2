<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 week', '+1 week');

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'division_id' => Division::factory(),
            'creator_id' => Account::factory()->manager(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'backlog',
            'start_date' => $startDate,
            'deadline' => fake()->dateTimeBetween($startDate, '+1 month'),
        ];
    }

    /**
     * Indicate that the task has been completed.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }
}
