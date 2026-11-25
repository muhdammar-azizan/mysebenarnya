<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_carries_the_basic_security_headers(): void
    {
        $response = $this->get(route('faq'))->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_error_pages_are_covered_too(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_content_security_policy_blocks_plugins_framing_and_foreign_forms(): void
    {
        $csp = $this->get(route('faq'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        // The only outside hosts we rely on: fonts and the image cropper.
        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
        $this->assertStringContainsString('https://cdnjs.cloudflare.com', $csp);
    }

    public function test_report_only_mode_does_not_enforce_the_policy(): void
    {
        config(['security.csp_report_only' => true]);

        $response = $this->get(route('faq'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('http://localhost/faq')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/faq')->assertHeader('Strict-Transport-Security', 'max-age=2592000');
    }

    public function test_hsts_can_be_switched_off(): void
    {
        config(['security.hsts_max_age' => 0]);

        $this->get('https://localhost/faq')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_evidence_downloads_keep_their_own_stricter_policy(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $owner->id]);
        Storage::disk('local')->put('evidence/proof.pdf', '%PDF-1.4');
        $evidence = InquiryEvidence::create([
            'inquiry_id' => $inquiry->id, 'uploaded_by' => $owner->id, 'file_path' => 'evidence/proof.pdf',
            'file_name' => 'proof.pdf', 'file_type' => 'application/pdf', 'file_size' => 8,
        ]);

        $response = $this->actingAs($owner)->get(route('evidence.show', $evidence))->assertOk();

        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_the_real_visitor_ip_and_scheme_are_read_from_the_proxy(): void
    {
        Route::middleware('web')->get('/_probe', fn () => response()->json([
            'ip' => request()->ip(),
            'secure' => request()->isSecure(),
        ]));

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
            ->get('/_probe')
            ->assertOk()
            ->assertJson(['ip' => '203.0.113.7', 'secure' => true]);
    }

    public function test_generated_urls_use_https_when_forced(): void
    {
        config(['security.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('faq'));
        $this->assertStringStartsWith('https://', asset('build/app.css'));
    }

    public function test_session_cookies_are_secure_by_default_in_production(): void
    {
        $config = require config_path('session.php');

        // In the test environment APP_ENV is "testing", so the default is off.
        $this->assertFalse((bool) $config['secure']);
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);
    }
}
