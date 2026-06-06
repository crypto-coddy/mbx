<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\SuperAdminService;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserPayoutDetailsTest extends TestCase
{

    public function test_admin_edit_page_shows_user_payout_details(): void
    {
        $admin = app(SuperAdminService::class)->ensure();
        $user = User::factory()->create();

        UserProfile::create([
            'user_id' => $user->id,
            'upi_id' => 'test2@sbi.co',
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'test',
            'account_number' => '876348764365643576',
            'ifsc_code' => 'TEST88787877',
            'account_type' => 'savings',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSee('Payout details')
            ->assertSee('test2@sbi.co')
            ->assertSee('Test Bank')
            ->assertSee('876348764365643576')
            ->assertSee('TEST88787877');
    }
}
