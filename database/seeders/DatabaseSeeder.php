<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Nursery;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Little Stars Group' => ['Bandra', 'Andheri'],
            'Happy Kids Group' => ['Pune'],
        ];

        foreach ($groups as $tenantTitle => $nurseries) {
            $tenant = Tenant::factory()->create(['title' => $tenantTitle]);

            foreach ($nurseries as $nurseryTitle) {
                $nursery = Nursery::factory()->for($tenant)->create(['title' => $nurseryTitle]);

                foreach (['Sunflowers', 'Bluebells'] as $roomTitle) {
                    Room::factory()->for($nursery)->create(['title' => $roomTitle]);
                }
            }

            Child::factory()->count(10)->for($tenant)->create();
        }
    }
}
