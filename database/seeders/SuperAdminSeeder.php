<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'superadmin@gmail.com');
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($password === '' && app()->environment('production')) {
            throw new RuntimeException('SUPER_ADMIN_PASSWORD must be set in production.');
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => $password !== '' ? $password : 'password',
                'is_active' => true,
            ],
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
