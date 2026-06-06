<?php

namespace Tests\Feature;

use App\Services\SuperAdminService;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FilamentAdminLoginTest extends TestCase
{

    public function test_super_admin_credentials_are_valid_for_filament(): void
    {
        $admin = app(SuperAdminService::class)->ensure();

        $this->assertTrue(Auth::attempt([
            'email' => 'admin@mbxzone.com',
            'password' => 'password',
        ]));

        $this->assertTrue($admin->canAccessPanel(filament()->getDefaultPanel()));
    }
}
