<?php

namespace Database\Seeders;

use App\Models\Plant;
use Illuminate\Database\Seeder;

class PlantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = [
            ['category' => 'Sayuran Daun', 'name' => 'Sawi'],
            ['category' => 'Sayuran Buah', 'name' => 'Cabai'],
        ];

        foreach ($plants as $plant) {
            Plant::create($plant);
        }
    }
}
