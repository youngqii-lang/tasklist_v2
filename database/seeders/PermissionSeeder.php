<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permission codes granted to each role, following the TASK-12 RBAC matrix.
     *
     * Admins act on every division; managers and supervisors only on the division they manage;
     * staff and freelancers only on the tasks assigned to them.
     *
     * @var array<string, list<string>>
     */
    public const array ROLE_PERMISSIONS = [
        Role::ADMIN => ['task.view_all', 'task.create', 'task.update_all', 'task.update_assigned', 'task.assign', 'task.review'],
        Role::MANAGER => ['task.view_all', 'task.create', 'task.update_all', 'task.assign', 'task.review'],
        Role::SUPERVISOR => ['task.view_all', 'task.create', 'task.update_all', 'task.assign', 'task.review'],
        Role::EMPLOYEE => ['task.view_all', 'task.update_assigned'],
        Role::FREELANCE => ['task.view_all', 'task.update_assigned'],
    ];

    /**
     * Seed the Tasklist permission codes and grant them to the roles. Run RoleSeeder first.
     */
    public function run(): void
    {
        $permissions = [
            ['code' => 'task.view_all', 'description' => 'Melihat seluruh task.'],
            ['code' => 'task.create', 'description' => 'Membuat task baru.'],
            ['code' => 'task.update_all', 'description' => 'Mengubah dan menghapus task dalam cakupan kewenangan (Admin lintas divisi, Manajer/Supervisor divisinya).'],
            ['code' => 'task.update_assigned', 'description' => 'Mengubah task yang ditugaskan ke akun sendiri.'],
            ['code' => 'task.assign', 'description' => 'Menugaskan atau melepas PIC task.'],
            ['code' => 'task.review', 'description' => 'Memberi keputusan review task.'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }

        foreach (self::ROLE_PERMISSIONS as $roleCode => $codes) {
            Role::where('code_role', $roleCode)->first()?->permissions()->sync(
                Permission::whereIn('code', $codes)->pluck('id')->all(),
            );
        }
    }
}
