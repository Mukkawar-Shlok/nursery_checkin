<?php

namespace App\Http\Resources;

use App\Models\RoomCheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomCheckIn */
class OccupantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'check_in_id' => $this->id,
            'child_id' => $this->child_id,
            'full_name' => $this->child->full_name,
            'guardian_name' => $this->child->guardian_name,
            'checked_in_at' => $this->checked_in_at,
        ];
    }
}
