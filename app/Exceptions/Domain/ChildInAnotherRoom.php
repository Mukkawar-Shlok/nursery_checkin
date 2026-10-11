<?php

namespace App\Exceptions\Domain;

use App\Models\Room;

final class ChildInAnotherRoom extends DomainException
{
    public function __construct(Room $room)
    {
        parent::__construct(
            409,
            'child_in_another_room',
            "Child is already checked in to {$room->title}. Check them out first.",
            ['room_id' => $room->id, 'room_title' => $room->title],
        );
    }
}
