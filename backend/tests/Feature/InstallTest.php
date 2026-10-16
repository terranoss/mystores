<?php

namespace Tests\Feature;

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

    public function test_execute_validates_required_parameters(): void
    {
        $response = $this->postJson('/api/install/execute', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'db_connection',
                'db_database',
                'app_name',
                'app_url',
                'admin_name',
                'admin_email',
                'admin_password',
                'store_name',
            ]);
    }
}
