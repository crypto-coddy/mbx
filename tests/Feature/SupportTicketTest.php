<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');
    }

    public function test_user_can_create_and_view_support_ticket(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Withdrawal delay',
            'category' => 'withdrawal',
            'message' => 'My withdrawal has been pending for 3 days.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'open');

        $ticketId = $create->json('data.id');

        $this->getJson("/api/v1/support/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Withdrawal delay');
    }
}
