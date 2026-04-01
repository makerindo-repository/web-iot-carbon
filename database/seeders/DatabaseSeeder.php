<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Super User',
            'email' => 'superuser@gmail.com',
            'password' => Hash::make('superuser'),
            'role' => 'superuser',
        ]);

        SubscriptionPlan::create([
            'name' => 'Free',
            'quota' => 30,
            'duration' => null,
            'price' => 0
        ]);

        UserSubscription::create([
            'user_id' => 1,
            'plan_id' => 1,
            'used_quota' => 0,
            'expires_at' => null
        ]);
    }
}
