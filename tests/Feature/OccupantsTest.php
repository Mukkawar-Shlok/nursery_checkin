<?php

namespace Tests\Feature;

use App\Models\RoomCheckIn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupantsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_lists_open_check_ins_oldest_first(): void
    {
        $room = $this->roomFor($this->tenant);
        $later = $this->checkInRow($this->childFor($this->tenant), $room, ['checked_in_at' => now()]);
        $earlier = $this->checkInRow($this->childFor($this->tenant), $room, ['checked_in_at' => now()->subHour()]);

        $this->asTenant($this->tenant)
            ->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertJsonPath('data.items.0.check_in_id', $earlier->id)
            ->assertJsonPath('data.items.1.check_in_id', $later->id)
            ->assertJsonStructure(['data' => ['items' => [['check_in_id', 'child_id', 'full_name', 'guardian_name', 'checked_in_at']]]]);
    }

    public function test_excludes_checked_out_and_soft_deleted_children(): void
    {
        $room = $this->roomFor($this->tenant);
        $this->checkInRow($this->childFor($this->tenant), $room, ['checked_out_at' => now()]);
        $deleted = $this->childFor($this->tenant);
        $this->checkInRow($deleted, $room);
        $this->softDelete($deleted);

        $this->asTenant($this->tenant)
            ->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_paginates(): void
    {
        $room = $this->roomFor($this->tenant);
        RoomCheckIn::factory()->count(30)->create(['tenant_id' => $this->tenant->id, 'room_id' => $room->id]);

        $this->asTenant($this->tenant)->getJson("/rooms/{$room->id}/occupants")
            ->assertJsonCount(25, 'data.items')
            ->assertJsonPath('data.pagination.total_count', 30)
            ->assertJsonPath('data.pagination.total_pages', 2);

        $this->asTenant($this->tenant)->getJson("/rooms/{$room->id}/occupants?page=2")
            ->assertJsonCount(5, 'data.items');

        $this->asTenant($this->tenant)->getJson("/rooms/{$room->id}/occupants?per_page=10&page=3")
            ->assertJsonCount(10, 'data.items');

        $this->asTenant($this->tenant)->getJson("/rooms/{$room->id}/occupants?per_page=500")
            ->assertJsonPath('data.pagination.per_page', 100)
            ->assertJsonCount(30, 'data.items');
    }

    public function test_empty_room_returns_empty_list(): void
    {
        $room = $this->roomFor($this->tenant);

        $this->asTenant($this->tenant)
            ->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.pagination.total_pages', 0);
    }
}
