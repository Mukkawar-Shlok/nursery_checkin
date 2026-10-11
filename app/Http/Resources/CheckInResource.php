<?php

namespace App\Http\Resources;

use App\Models\RoomCheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomCheckIn */
class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'child_id' => $this->child_id,
            'room_id' => $this->room_id,
            'checked_in_at' => $this->checked_in_at,
            'checked_out_at' => $this->checked_out_at,
        ];
    }
}
