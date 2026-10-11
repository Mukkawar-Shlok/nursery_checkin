<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Child> */
class ChildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'full_name' => fake()->name(),
            'guardian_name' => fake()->name(),
            // 10-digit Indian mobile number: starts with 6-9.
            'guardian_contact_number' => fake()->regexify('[6-9][0-9]{9}'),
        ];
    }
}
