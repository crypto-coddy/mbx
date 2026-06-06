<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }

    public function test_admin_login_includes_admin_csp(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('frame-ancestors', $csp);
    }

    public function test_cors_allows_configured_origin_preflight(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/v1/auth/login',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:8081',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
            ],
        );

        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8081');
        $response->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_cors_does_not_reflect_disallowed_origin(): void
    {
        $response = $this->getJson('/api/v1/', [
            'Origin' => 'https://evil.example',
        ]);

        $response->assertOk();
        $this->assertNotSame(
            'https://evil.example',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }
}
