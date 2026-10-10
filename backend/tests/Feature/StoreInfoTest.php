<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_store_info_endpoint_returns_ready_status(): void
    {
        $user = \App\Models\User::factory()->create();
        Workspace::create([
            'name' => 'Acme Test Mart',
            'owner_user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/store-info');

        $response->assertStatus(200)
            ->assertJson([
                'app' => 'mystore',
                'name' => 'Acme Test Mart',
                'status' => 'ready',
            ]);
    }
}
