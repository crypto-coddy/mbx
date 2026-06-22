<?php

namespace Tests\Feature;

use App\Models\DepositUpiId;
use App\Services\UpiQrService;
use Tests\TestCase;

class UpiQrServiceTest extends TestCase
{
    public function test_payment_uri_includes_upi_id_and_payee_name(): void
    {
        $uri = app(UpiQrService::class)->paymentUri('quantx@upi', 'QuantX Payments');

        $this->assertStringStartsWith('upi://pay?', $uri);
        $this->assertStringContainsString('pa=quantx%40upi', $uri);
        $this->assertStringContainsString('pn=QuantX%20Payments', $uri);
        $this->assertStringContainsString('cu=INR', $uri);
    }

    public function test_deposit_instructions_include_qr_payload_for_active_upi(): void
    {
        DepositUpiId::query()->update(['is_active' => false]);

        DepositUpiId::create([
            'label' => 'Primary',
            'upi_id' => 'scan@upi',
            'payee_name' => 'QuantX',
            'show_qr_code' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/deposits/instructions')->assertOk();

        $response->assertJsonPath('data.upi_ids.0.upi_id', 'scan@upi');
        $response->assertJsonPath('data.upi_ids.0.payee_name', 'QuantX');
        $response->assertJsonPath('data.upi_ids.0.show_qr_code', true);
        $this->assertStringStartsWith(
            'upi://pay?',
            (string) $response->json('data.upi_ids.0.upi_uri'),
        );
    }

    public function test_deposit_instructions_hide_qr_uri_when_disabled(): void
    {
        DepositUpiId::query()->update(['is_active' => false]);

        DepositUpiId::create([
            'upi_id' => 'textonly@upi',
            'show_qr_code' => false,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $user = \App\Models\User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/deposits/instructions')->assertOk();

        $response->assertJsonPath('data.upi_ids.0.show_qr_code', false);
        $response->assertJsonPath('data.upi_ids.0.upi_uri', null);
    }

    public function test_svg_markup_renders_for_upi_id(): void
    {
        $svg = app(UpiQrService::class)->svgMarkup('quantx@upi', 'QuantX');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $svg);
    }
}
