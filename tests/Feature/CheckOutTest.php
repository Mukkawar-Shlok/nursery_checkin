<?php

namespace Tests\Feature;

use App\Constants\APIResponseCodes;
use App\Models\RoomCheckIn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checks_a_child_out(): void
    {
        $tenant = Tenant::factory()->create();
        $checkIn = $this->checkInRow($this->childFor($tenant), $this->roomFor($tenant));

        $this->asTenant($tenant)
            ->postJson("/check-ins/{$checkIn->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.id', $checkIn->id)
            ->assertJsonPath('data.checked_out_at', fn ($value) => $value !== null);

        $this->assertDatabaseMissing('room_check_ins', ['id' => $checkIn->id, 'checked_out_at' => null]);
    }

    public function test_second_check_out_returns_409(): void
    {
        $tenant = Tenant::factory()->create();
        $checkIn = $this->checkInRow($this->childFor($tenant), $this->roomFor($tenant));

        $this->asTenant($tenant)->postJson("/check-ins/{$checkIn->id}/check-out")->assertOk();
        $this->asTenant($tenant)
            ->postJson("/check-ins/{$checkIn->id}/check-out")
            ->assertConflict()
            ->assertJsonPath('code', APIResponseCodes::CONFLICT)
            ->assertJsonPath('message', __('api.errors.already_checked_out'));
    }

    public function test_unknown_check_in_returns_404(): void
    {
        $this->asTenant(Tenant::factory()->create())
            ->postJson('/check-ins/'.Str::uuid7().'/check-out')
            ->assertNotFound()
            ->assertJsonPath('status_code', 404)
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(RoomCheckIn::class));
    }
}
