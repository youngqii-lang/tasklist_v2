<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('now', '+1 month');

        return [
            'division_id' => Division::factory(),
            'creator_id' => Account::factory()->manager(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify('+2 hours'),
        ];
    }

    /**
     * Indicate that the event is company-wide rather than owned by a division.
     */
    public function companyWide(): static
    {
        return $this->state(fn (array $attributes) => [
            'division_id' => null,
        ]);
    }
}
