<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Seed the default accounts, since the application has no registration.
     *
     * The Manager runs the Marketing division; Admin works across every division.
     */
    public function run(): void
    {
        $accounts = [
            ['username' => 'manager', 'fullname' => 'Default Manager', 'role' => Role::MANAGER, 'division' => 'MKT'],
            ['username' => 'admin', 'fullname' => 'Admin', 'role' => Role::ADMIN, 'division' => null],
        ];

        foreach ($accounts as $account) {
            Account::updateOrCreate(
                ['username' => $account['username']],
                [
                    'role_id' => Role::where('code_role', $account['role'])->valueOrFail('id'),
                    'division_id' => $account['division'] ? Division::where('code_division', $account['division'])->value('id') : null,
                    'fullname' => $account['fullname'],
                    'password' => 'password',
                ],
            );
        }
    }
}
