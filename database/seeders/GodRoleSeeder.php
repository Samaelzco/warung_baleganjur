<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GodRoleSeeder extends Seeder
{
    public function run(): void
    {
        try {
            if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_has_permissions')) {
                return;
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $defaultPermissions = [
                'dashboard.access',
                'meja.access',
                'meja.manage',
                'kategori.access',
                'kategori.manage',
                'menu.access',
                'menu.manage',
                'addon.access',
                'addon.manage',
                'pajak.access',
                'pajak.manage',
                'diskon.access',
                'diskon.manage',
                'pesanan.access',
                'pesanan.manage',
                'waiting-list.access',
                'waiting-list.manage',
                'kitchen.access',
                'kitchen.manage',
                'pembayaran.access',
                'pembayaran.manage',
                'users.access',
                'users.manage',
                'roles.access',
                'roles.manage',
            ];

            foreach ($defaultPermissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $godRole = Role::firstOrCreate(['name' => 'God', 'guard_name' => 'web']);
            $godRole->syncPermissions(Permission::query()->pluck('name')->all());

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore if permission tables aren't migrated yet.
        }
    }
}
