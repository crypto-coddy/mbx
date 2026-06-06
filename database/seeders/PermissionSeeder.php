<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'access_admin_panel',
        'view_dashboard',
        'view_customers',
        'manage_customers',
        'recharge_wallet',
        'view_markets',
        'manage_markets',
        'view_trades',
        'manage_trades',
        'view_kyc',
        'manage_kyc',
        'view_withdrawals',
        'manage_withdrawals',
        'view_deposits',
        'manage_deposits',
        'view_admin_users',
        'manage_admin_users',
        'manage_roles',
        'view_blog',
        'manage_blog',
        'view_mobile_config',
        'manage_mobile_config',
        'view_support',
        'manage_support',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $all = Permission::where('guard_name', 'web')->pluck('name')->all();

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions($all);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->syncPermissions(array_values(array_diff($all, [
                'manage_admin_users',
                'manage_roles',
            ])));
        }
    }
}
