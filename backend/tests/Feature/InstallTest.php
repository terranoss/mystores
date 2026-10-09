<?php

namespace Tests\Feature;

use App\Services\Licensing\LicenseService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallTest extends TestCase
{
    protected function tearDown(): void
    {
        // Ensure installed lock is cleaned up after testing
        if (File::exists(storage_path('installed'))) {
            File::delete(storage_path('installed'));
        }
        if (File::exists(storage_path('app/license.json'))) {
            File::delete(storage_path('app/license.json'));
        }

        parent::tearDown();
    }

    public function test_install_status_returns_uninstalled_when_lock_missing(): void
    {
        $response = $this->getJson('/api/install/status');

        $response->assertStatus(200)
            ->assertJson([
                'installed' => false,
            ]);
    }

    public function test_preflight_checks_return_system_status(): void
    {
        $response = $this->getJson('/api/install/preflight');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ready',
                'checks' => [
                    'php_version',
                    'pdo',
                    'openssl',
                    'mbstring',
                    'curl',
                    'storage_writable',
                    'cache_writable',
                ],
            ]);
    }

    public function test_test_db_validates_required_parameters(): void
    {
        $response = $this->postJson('/api/install/test-db', []);

        $response->assertStatus(422);
    }

    public function test_activate_license_rejects_missing_key(): void
    {
        $response = $this->postJson('/api/install/activate-license', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_key']);
    }

    public function test_license_service_computes_consistent_fingerprint(): void
    {
        $service = app(LicenseService::class);
        $fp1 = $service->computeFingerprint('example.com');
        $fp2 = $service->computeFingerprint('example.com');

        $this->assertEquals(64, strlen($fp1));
        $this->assertEquals($fp1, $fp2);
    }

    public function test_jwt_signature_verification_rejects_tampered_token(): void
    {
        $service = app(LicenseService::class);
        $fakeToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWV9.fake_signature';

        $this->assertNull($service->verifyJwtToken($fakeToken));
    }
}
