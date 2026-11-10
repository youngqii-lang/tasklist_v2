<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    /**
     * The demo roles each division gets, keyed by username prefix.
     *
     * @var array<string, array{role: string, label: string}>
     */
    public const array ROLES = [
        'spv' => ['role' => Role::SUPERVISOR, 'label' => 'Supervisor'],
        'staff' => ['role' => Role::EMPLOYEE, 'label' => 'Staff'],
        'freelance' => ['role' => Role::FREELANCE, 'label' => 'Freelance'],
    ];

    /**
     * Seed a Supervisor, an Employee and a Freelancer for every division, such as `spv.seo`.
     */
    public function run(): void
    {
        $roleIds = Role::pluck('id', 'code_role');

        foreach (Division::whereIn('code_division', array_keys(DivisionSeeder::DIVISIONS))->get() as $division) {
            foreach (self::ROLES as $prefix => $role) {
                Account::updateOrCreate(
                    ['username' => self::username($prefix, $division->code_division)],
                    [
                        'role_id' => $roleIds[$role['role']],
                        'division_id' => $division->id,
                        'fullname' => "{$role['label']} {$division->name}",
                        'password' => 'password',
                    ],
                );
            }
        }
    }

    /**
     * Build the demo username of a role in a division.
     */
    public static function username(string $prefix, string $divisionCode): string
    {
        return $prefix.'.'.strtolower($divisionCode);
    }
}
