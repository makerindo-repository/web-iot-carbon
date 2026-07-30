<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production') && ! env('ALLOW_DEMO_USERS', false)) {
            $this->command?->warn('Demo users were not seeded in production. Set ALLOW_DEMO_USERS=true only for temporary staging data.');

            return;
        }

        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'superuser@gmail.com'],
            [
                'name' => 'Super User',
                'password' => Hash::make('superuser'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'dosen@agrisense.id'],
            [
                'name' => 'Dosen Peneliti',
                'password' => Hash::make('password123'),
                'role' => 'operator',
            ]
        );

        User::updateOrCreate(
            ['email' => 'mahasiswa@agrisense.id'],
            [
                'name' => 'Mahasiswa Magang',
                'password' => Hash::make('password123'),
                'role' => 'viewer',
            ]
        );
    }
}
