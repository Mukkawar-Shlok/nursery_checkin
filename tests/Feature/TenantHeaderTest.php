<?php

namespace Tests\Feature;

use App\Constants\APIResponseCodes;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_header_returns_400(): void
    {
        $room = $this->roomFor(Tenant::factory()->create());

        $this->getJson("/rooms/{$room->id}/occupants")
            ->assertStatus(400)
            ->assertJsonPath('code', APIResponseCodes::BAD_REQUEST);
    }

    public function test_non_uuid_header_returns_400(): void
    {
        $room = $this->roomFor(Tenant::factory()->create());

        $this->withHeader('X-Tenant-ID', 'not-a-uuid')
            ->getJson("/rooms/{$room->id}/occupants")
            ->assertStatus(400)
            ->assertJsonPath('code', APIResponseCodes::BAD_REQUEST);
    }

    public function test_unknown_tenant_returns_404(): void
    {
        $room = $this->roomFor(Tenant::factory()->create());

        $this->withHeader('X-Tenant-ID', (string) Str::uuid7())
            ->getJson("/rooms/{$room->id}/occupants")
            ->assertNotFound()
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(Tenant::class));
    }
}
