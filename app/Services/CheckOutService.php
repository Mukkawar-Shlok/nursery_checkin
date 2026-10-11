<?php

namespace App\Services;

use App\Models\RoomCheckIn;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CheckOutService
{
    /**
     * One atomic conditional UPDATE, so a double check-out cannot race.
     * Never rewrite this as load, check in PHP, then save.
     */
    public function checkOut(string $id): RoomCheckIn
    {
        $updated = RoomCheckIn::whereKey($id)
            ->whereNull('checked_out_at')
            ->update(['checked_out_at' => now()]);

        $checkIn = RoomCheckIn::findOrFail($id);

        if ($updated === 0) {
            throw new ConflictHttpException(__('api.errors.already_checked_out'));
        }

        return $checkIn;
    }
}
