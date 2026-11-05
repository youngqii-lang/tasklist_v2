<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the Tasklist permission codes. Role mapping waits for the SA permission matrix.
     */
    public function run(): void
    {
        $permissions = [
            ['code' => 'task.view_all', 'description' => 'Melihat seluruh task.'],
            ['code' => 'task.create', 'description' => 'Membuat task baru.'],
            ['code' => 'task.update_all', 'description' => 'Mengubah seluruh task lintas divisi.'],
            ['code' => 'task.update_assigned', 'description' => 'Mengubah task yang ditugaskan ke akun sendiri.'],
            ['code' => 'task.assign', 'description' => 'Menugaskan atau melepas PIC task.'],
            ['code' => 'task.review', 'description' => 'Memberi keputusan review task.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }
    }
}
