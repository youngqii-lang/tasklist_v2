<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Seed the default accounts, since the application has no registration.
     */
    public function run(): void
    {
        $accounts = [
            ['username' => 'manager', 'fullname' => 'Default Manager', 'role' => Role::MANAGER],
            ['username' => 'admin', 'fullname' => 'Admin', 'role' => Role::ADMIN],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['username' => $account['username']],
                [
                    'role_id' => Role::where('code_role', $account['role'])->valueOrFail('id'),
                    'division_id' => null,
                    'fullname' => $account['fullname'],
                    'password' => 'password',
                ],
            );
        }
    }
}
