<?php

namespace Database\Factories;

use App\Models\Nursery;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nursery_id' => Nursery::factory(),
            // Always the nursery's tenant, so a room can never straddle tenants.
            'tenant_id' => fn (array $attributes) => Nursery::find($attributes['nursery_id'])->tenant_id,
            'title' => fake()->randomElement(['Sunflowers', 'Bluebells', 'Daisies', 'Tulips']),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
