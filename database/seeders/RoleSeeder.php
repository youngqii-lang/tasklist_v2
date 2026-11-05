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
            ['code_role' => Role::MANAGER, 'name' => 'Manager'],
            ['code_role' => Role::EMPLOYEE, 'name' => 'Employee'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code_role' => $role['code_role']], $role);
        }
    }
}
