<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Services\SuperAdminService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserPasswordUpdateTest extends TestCase
{

    public function test_admin_can_update_user_password_on_edit(): void
    {
        $admin = app(SuperAdminService::class)->ensure();
        $user = User::factory()->create(['password' => 'old-password-1']);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertFalse(Hash::check('old-password-1', $user->password));
    }

    public function test_blank_password_leaves_existing_password_unchanged(): void
    {
        $admin = app(SuperAdminService::class)->ensure();
        $user = User::factory()->create(['password' => 'keep-this-password']);
        $hashBefore = $user->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($hashBefore, $user->password);
    }
}
