<?php

namespace App\Services;

use App\Models\Child;
use App\Models\Room;
use App\Models\RoomCheckIn;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CheckInService
{
    /**
     * Invariant: a child has at most one open check-in. Locking the child row
     * (SELECT ... FOR UPDATE) queues concurrent check-ins for the same child,
     * so the second one sees the first one's committed row (READ COMMITTED).
     */
    public function checkIn(Room $room, string $childId): RoomCheckIn
    {
        return DB::transaction(function () use ($room, $childId) {
            $child = Child::lockForUpdate()->findOrFail($childId);

            $open = RoomCheckIn::where('child_id', $child->id)->whereNull('checked_out_at')->first();

            if ($open?->room_id === $room->id) {
                throw new ConflictHttpException(__('api.errors.child_already_checked_in'));
            }

            if ($open) {
                throw new ConflictHttpException(__('api.errors.child_in_another_room', ['room' => $open->room->title]));
            }

            return RoomCheckIn::create([
                'tenant_id' => $room->tenant_id,
                'child_id' => $child->id,
                'room_id' => $room->id,
                'checked_in_at' => now(),
            ]);
        });
    }
}
