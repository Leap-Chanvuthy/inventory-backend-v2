<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Enums\UserRoleEnum; // if you use enum for role

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    public function definition(): array
    {
        $names = [
            'Alice Johnson', 'Bob Smith', 'Charlie Brown', 'David Wilson', 'Eve Thompson',
            'Frank Miller', 'Grace Lee', 'Hannah Taylor', 'Ian Anderson', 'Jane Martinez',
            'Kevin White', 'Laura Harris', 'Michael Clark', 'Nina Lewis', 'Oscar Young',
            'Paula Hall', 'Quinn Allen', 'Rachel King', 'Steve Wright', 'Tina Scott',
            'Umar Adams', 'Victoria Baker', 'William Nelson', 'Xander Roberts', 'Yara Perez',
            'Zack Turner', 'Olivia Hill', 'Liam Green', 'Sophia Adams', 'Mason Carter'
        ];

        $name = $this->faker->unique()->randomElement($names);
        $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
        $phoneNumber = '09' . $this->faker->numberBetween(10000000, 99999999);
        $randomId = rand(1, 30);

        $roles = ['ADMIN', 'STOCK_CONTROLLER' , 'VENDER' ]; 


        return [
            'name' => $name,
            'email' => $email,
            'phone_number' => $phoneNumber,
            'role' => $this->faker->randomElement($roles),
            'password' => Hash::make('password123'), // default password
            'profile_picture' => "https://avatar.iran.liara.run/public/{$randomId}",
            'email_verified_at' => now(),
        ];
    }
}
