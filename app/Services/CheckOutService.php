<?php

namespace App\Services;

use App\Exceptions\Domain\CheckInAlreadyClosed;
use App\Models\RoomCheckIn;

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
            throw new CheckInAlreadyClosed;
        }

        return $checkIn;
    }
}
