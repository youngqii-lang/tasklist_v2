<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        $roles = [
            ['code_role' => Role::ADMIN, 'name' => 'Admin'],
            ['code_role' => Role::MANAGER, 'name' => 'Manager'],
            ['code_role' => Role::SUPERVISOR, 'name' => 'Supervisor'],
            ['code_role' => Role::EMPLOYEE, 'name' => 'Employee'],
            ['code_role' => Role::FREELANCE, 'name' => 'Freelance'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code_role' => $role['code_role']], $role);
        }
    }
}
