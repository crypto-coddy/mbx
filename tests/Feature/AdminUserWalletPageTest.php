<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\SuperAdminService;
use Tests\TestCase;

class AdminUserWalletPageTest extends TestCase
{

    public function test_edit_page_works_for_comparison(): void
    {
        $admin = app(SuperAdminService::class)->ensure();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(UserResource::getUrl('edit', ['record' => $user]))
            ->assertOk();
    }

    public function test_wallet_page_is_reachable_for_admin(): void
    {
        $admin = app(SuperAdminService::class)->ensure();
        $user = User::factory()->create();

        $url = UserResource::getUrl('wallet', ['record' => $user]);

        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertSee('Transaction history');
    }
}
