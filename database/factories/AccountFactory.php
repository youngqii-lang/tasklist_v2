<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => fn () => Role::firstOrCreate(['code_role' => Role::EMPLOYEE], ['name' => 'Employee'])->id,
            'division_id' => Division::factory(),
            'fullname' => fake()->name(),
            'username' => fake()->unique()->bothify('user??????##'),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Indicate that the account has the admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['code_role' => Role::ADMIN], ['name' => 'Admin'])->id,
        ]);
    }

    /**
     * Indicate that the account has the supervisor role.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['code_role' => Role::SUPERVISOR], ['name' => 'Supervisor'])->id,
        ]);
    }

    /**
     * Indicate that the account has the freelance role.
     */
    public function freelance(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['code_role' => Role::FREELANCE], ['name' => 'Freelance'])->id,
        ]);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the account has the manager role.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['code_role' => Role::MANAGER], ['name' => 'Manager'])->id,
        ]);
    }

    /**
     * Indicate that the account has the employee role.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['code_role' => Role::EMPLOYEE], ['name' => 'Employee'])->id,
        ]);
    }

    /**
     * Indicate that the account is not assigned to any division.
     */
    public function withoutDivision(): static
    {
        return $this->state(fn (array $attributes) => [
            'division_id' => null,
        ]);
    }
}
