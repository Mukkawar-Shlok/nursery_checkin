<?php

namespace Tests;

use App\Models\Child;
use App\Models\Nursery;
use App\Models\Room;
use App\Models\RoomCheckIn;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function asTenant(Tenant $tenant): static
    {
        return $this->withHeader('X-Tenant-ID', $tenant->id);
    }

    protected function roomFor(Tenant $tenant, array $attributes = []): Room
    {
        return Room::factory()->for(Nursery::factory()->for($tenant))->create($attributes);
    }

    protected function childFor(Tenant $tenant, array $attributes = []): Child
    {
        return Child::factory()->for($tenant)->create($attributes);
    }

    protected function checkInRow(Child $child, Room $room, array $attributes = []): RoomCheckIn
    {
        return RoomCheckIn::factory()->create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'child_id' => $child->id,
            ...$attributes,
        ]);
    }

    /** Bypasses the tenant scope on purpose: tests run outside any request. */
    protected function softDelete(Model $model): void
    {
        DB::table($model->getTable())->where('id', $model->id)->update(['deleted_at' => now()]);
    }
}
