<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true],
        );

        try {
            if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
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
                }

                $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

                if (Schema::hasTable('permissions') && Schema::hasTable('role_has_permissions')) {
                    $superAdminRole->syncPermissions(Permission::query()->pluck('name')->all());
                }

                $user->syncRoles([$superAdminRole->name]);

                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable $e) {
            // Ignore if permission tables aren't migrated yet.
        }
    }
}
