<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomCheckIn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OccupantService
{
    public function forRoom(Room $room, int $perPage = 25): LengthAwarePaginator
    {
        return RoomCheckIn::where('room_id', $room->id)
            ->whereNull('checked_out_at')
            ->whereHas('child') // excludes soft-deleted children
            ->with('child')
            ->orderBy('checked_in_at')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 100));
    }
}
