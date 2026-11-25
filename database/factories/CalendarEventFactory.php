<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state: a shared agenda without an organiser, covering whole days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 weeks', '+2 weeks')->setTime(0, 0);

        return [
            'division_id' => null,
            'creator_id' => fn () => $this->defaultCreatorId(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'is_shared' => true,
            'start_at' => $start,
            'end_at' => fake()->optional()->dateTimeBetween($start, (clone $start)->modify('+5 days'))?->setTime(0, 0),
        ];
    }

    /**
     * An internal agenda for the given divisions, organised by the first: only Admin and their accounts see it.
     * Without a division only Admin sees it.
     */
    public function internalTo(Division ...$targets): static
    {
        return $this->state(['is_shared' => false, 'division_id' => $targets[0]->id ?? null])
            ->afterCreating(fn (CalendarEvent $event) => $event->targetDivisions()->attach(array_map(fn (Division $division) => $division->id, $targets)));
    }

    /**
     * The first Admin account, created when there is none yet.
     */
    private function defaultCreatorId(): int
    {
        $admin = Account::whereHas('role', fn ($query) => $query->where('code_role', Role::ADMIN))->orderBy('id')->first();

        if ($admin !== null) {
            return $admin->id;
        }

        $role = Role::firstOrCreate(['code_role' => Role::ADMIN], ['name' => 'Admin']);

        return Account::create([
            'role_id' => $role->id,
            'fullname' => 'Agenda Admin',
            'username' => 'agenda.admin.'.fake()->unique()->numerify('####'),
            'password' => 'password',
        ])->id;
    }
}
