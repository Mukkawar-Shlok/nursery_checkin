<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Room;
use App\Models\RoomCheckIn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<RoomCheckIn> */
class RoomCheckInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'tenant_id' => fn (array $attributes) => DB::table('rooms')->where('id', $attributes['room_id'])->value('tenant_id'),
            'child_id' => fn (array $attributes) => Child::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'checked_in_at' => now(),
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(['checked_out_at' => now()]);
    }
}
