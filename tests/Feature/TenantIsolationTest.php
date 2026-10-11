<?php

namespace Tests\Feature;

use App\Constants\APIResponseCodes;
use App\Models\Child;
use App\Models\Room;
use App\Models\RoomCheckIn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
    }

    public function test_cannot_list_occupants_of_another_tenants_room(): void
    {
        $roomB = $this->roomFor($this->b);

        $this->asTenant($this->a)
            ->getJson("/rooms/{$roomB->id}/occupants")
            ->assertNotFound()
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(Room::class));
    }

    public function test_cannot_check_out_another_tenants_check_in(): void
    {
        $checkInB = $this->checkInRow($this->childFor($this->b), $this->roomFor($this->b));

        $this->asTenant($this->a)
            ->postJson("/check-ins/{$checkInB->id}/check-out")
            ->assertNotFound()
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(RoomCheckIn::class));

        $this->assertDatabaseHas('room_check_ins', ['id' => $checkInB->id, 'checked_out_at' => null]);
    }

    public function test_cannot_check_another_tenants_child_into_own_room(): void
    {
        $roomA = $this->roomFor($this->a);
        $childB = $this->childFor($this->b);

        $this->asTenant($this->a)
            ->postJson("/rooms/{$roomA->id}/check-ins", ['child_id' => $childB->id])
            ->assertNotFound()
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(Child::class));

        $this->assertDatabaseCount('room_check_ins', 0);
    }

    public function test_cannot_check_into_another_tenants_room(): void
    {
        $roomB = $this->roomFor($this->b);
        $childA = $this->childFor($this->a);

        $this->asTenant($this->a)
            ->postJson("/rooms/{$roomB->id}/check-ins", ['child_id' => $childA->id])
            ->assertNotFound()
            ->assertJsonPath('code', APIResponseCodes::NOT_FOUND)
            ->assertJsonPath('errors.model', $this->mentionsModel(Room::class));

        $this->assertDatabaseCount('room_check_ins', 0);
    }

    public function test_occupants_never_include_other_tenants_children(): void
    {
        $roomA = $this->roomFor($this->a, ['title' => 'Sunflowers']);
        $roomB = $this->roomFor($this->b, ['title' => 'Sunflowers']);
        $childA = $this->childFor($this->a);
        $this->checkInRow($childA, $roomA);
        $this->checkInRow($this->childFor($this->b), $roomB);

        $this->asTenant($this->a)
            ->getJson("/rooms/{$roomA->id}/occupants")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.child_id', $childA->id);
    }
}
