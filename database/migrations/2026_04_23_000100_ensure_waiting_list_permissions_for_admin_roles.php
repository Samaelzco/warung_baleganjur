<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'waiting-list.access',
            'waiting-list.manage',
        ];

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        Role::query()
            ->where('name', 'Super Admin')
            ->each(function (Role $role) use ($permissionNames) {
                $role->givePermissionTo($permissionNames);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'waiting-list.access',
            'waiting-list.manage',
        ];

        Role::query()
            ->where('name', 'Super Admin')
            ->each(function (Role $role) use ($permissionNames) {
                $role->revokePermissionTo($permissionNames);
            });

        Permission::query()
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
