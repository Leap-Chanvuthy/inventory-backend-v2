<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

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

        $roleId = Role::query()
            ->inRandomOrder()
            ->value('id');


        return [
            'name' => $name,
            'email' => $email,
            'phone_number' => $phoneNumber,
            'role_id' => $roleId,
            'password' => Hash::make('password123'), // default password
            'profile_picture' => "https://api.dicebear.com/9.x/adventurer/svg?seed={$randomId}",
            'email_verified_at' => now(),
        ];
    }
}
