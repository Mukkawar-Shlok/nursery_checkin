<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_checks_a_child_in(): void
    {
        $room = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])
            ->assertCreated()
            ->assertJsonPath('data.child_id', $child->id)
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.checked_out_at', null);

        $this->assertDatabaseHas('room_check_ins', [
            'tenant_id' => $this->tenant->id,
            'child_id' => $child->id,
            'room_id' => $room->id,
            'checked_out_at' => null,
        ]);
    }

    public function test_same_room_twice_returns_409(): void
    {
        $room = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);
        $this->checkInRow($child, $room);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])
            ->assertConflict()
            ->assertJsonPath('error.code', 'child_already_checked_in');
    }

    public function test_child_in_another_room_returns_409_naming_that_room(): void
    {
        $sunflowers = $this->roomFor($this->tenant, ['title' => 'Sunflowers']);
        $bluebells = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);
        $this->checkInRow($child, $sunflowers);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$bluebells->id}/check-ins", ['child_id' => $child->id])
            ->assertConflict()
            ->assertJsonPath('error.code', 'child_in_another_room')
            ->assertJsonPath('error.room_id', $sunflowers->id)
            ->assertJsonPath('error.room_title', 'Sunflowers');
    }

    public function test_child_checked_out_of_one_room_can_check_into_another(): void
    {
        $roomA = $this->roomFor($this->tenant);
        $roomB = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);
        $this->checkInRow($child, $roomA, ['checked_out_at' => now()]);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$roomB->id}/check-ins", ['child_id' => $child->id])
            ->assertCreated();
    }

    public function test_sequential_check_ins_into_two_rooms_only_one_succeeds(): void
    {
        $roomA = $this->roomFor($this->tenant);
        $roomB = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$roomA->id}/check-ins", ['child_id' => $child->id])
            ->assertCreated();
        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$roomB->id}/check-ins", ['child_id' => $child->id])
            ->assertConflict();

        $this->assertDatabaseCount('room_check_ins', 1);
    }

    public function test_missing_or_invalid_child_id_returns_422(): void
    {
        $room = $this->roomFor($this->tenant);

        foreach ([[], ['child_id' => 'nope']] as $body) {
            $this->asTenant($this->tenant)
                ->postJson("/rooms/{$room->id}/check-ins", $body)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonStructure(['error' => ['fields' => ['child_id']]]);
        }
    }

    public function test_soft_deleted_child_returns_404(): void
    {
        $room = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);
        $this->softDelete($child);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'child_not_found');
    }

    public function test_soft_deleted_room_returns_404(): void
    {
        $room = $this->roomFor($this->tenant);
        $child = $this->childFor($this->tenant);
        $this->softDelete($room);

        $this->asTenant($this->tenant)
            ->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])
            ->assertNotFound();
    }
}
