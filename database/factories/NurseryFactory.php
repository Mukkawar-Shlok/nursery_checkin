<?php

namespace Database\Factories;

use App\Models\Nursery;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Nursery> */
class NurseryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->city(),
        ];
    }
}
