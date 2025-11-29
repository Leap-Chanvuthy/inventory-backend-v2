<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Generate 30 users using the factory
        User::factory()->count(30)->create();
    }
}
