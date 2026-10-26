<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_pages_are_served_with_security_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_error_pages_are_served_with_security_headers(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_https_responses_include_strict_transport_security(): void
    {
        $response = $this->get('https://localhost/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_http_responses_do_not_include_strict_transport_security(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk();
        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
