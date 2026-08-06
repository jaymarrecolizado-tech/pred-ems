<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Default roles and coarse-grained permissions.
     * Permissions follow a {verb}.{resource} convention so the middleware
     * check is a single helper: `$user->hasPermission('view.employees')`.
     */
    public function run(): void
    {
        $permissions = [
            'view.employees', 'create.employees', 'update.employees', 'delete.employees',
            'view.appointments', 'create.appointments', 'update.appointments',
            'view.leave', 'create.leave', 'approve.leave',
            'view.payroll', 'run.payroll', 'finalize.payroll',
            'view.contributions', 'manage.remittances',
            'view.documents', 'issue.documents',
            'view.reports', 'view.audit', 'manage.users', 'manage.roles',
        ];

        $roleMap = [
            'admin' => $permissions,
            'hr' => [
                'view.employees', 'create.employees', 'update.employees',
                'view.appointments', 'create.appointments', 'update.appointments',
                'view.leave', 'create.leave', 'approve.leave',
                'view.documents', 'issue.documents', 'view.reports',
            ],
            'payroll' => [
                'view.employees', 'view.payroll', 'run.payroll', 'finalize.payroll',
                'view.contributions', 'manage.remittances', 'view.reports',
            ],
            'unit_head' => [
                'view.employees', 'view.leave', 'approve.leave', 'view.reports',
            ],
            'employee' => [
                'view.leave', 'create.leave',
            ],
        ];

        $now = now();
        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm],
                ['guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($roleMap as $role => $perms) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['guard_name' => 'web', 'description' => ucfirst(str_replace('_', ' ', $role)), 'created_at' => $now, 'updated_at' => $now]
            );
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            $permIds = DB::table('permissions')->whereIn('name', $perms)->pluck('id');

            foreach ($permIds as $permId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permId],
                    []
                );
            }
        }
    }
}
