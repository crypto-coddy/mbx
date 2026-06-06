<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminUserResource\Pages\CreateAdminUser;
use App\Models\User;
use App\Services\SuperAdminService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{

    public function test_super_admin_can_create_admin_user(): void
    {
        $this->seedRolesAndPermissions();
        $super = app(SuperAdminService::class)->ensure();

        $this->actingAs($super);

        Livewire::test(CreateAdminUser::class)
            ->fillForm([
                'name' => 'Finance Admin',
                'email' => 'finance@mbxzone.com',
                'phone' => '8888888881',
                'password' => 'password123',
                'admin_role' => 'admin',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $admin = User::where('email', 'finance@mbxzone.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('view_customers'));
        $this->assertFalse($admin->can('manage_admin_users'));
    }

    public function test_audit_columns_exist_on_users_table(): void
    {
        $this->seedRolesAndPermissions();

        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumns('users', ['created_by', 'updated_by', 'created_at', 'updated_at'])
        );
    }

    private function seedRolesAndPermissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }
}
