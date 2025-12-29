<?php

namespace App\Console\Commands;

use App\Enums\UserRoleEnum;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin';
    protected $description = 'Create a new admin user with email and password input';

    public function handle()
    {
        $this->info('🚀 Create a new Admin User');

        $email = $this->ask('Enter admin email');

        if (User::where('email', $email)->exists()) {
            $this->error('❌ A user with this email already exists!');
            return Command::FAILURE;
        }

        $password = $this->secret('Enter admin password');
        $confirmPassword = $this->secret('Confirm password');

        if ($password !== $confirmPassword) {
            $this->error('❌ Passwords do not match!');
            return Command::FAILURE;
        }

        $user = User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRoleEnum::ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->info('✅ Admin user created successfully!');
        $this->table(['ID', 'Name', 'Email', 'Role', 'Email Verified At'], [
            [
                $user->id,
                $user->name,
                $user->email,
                $user->role->value,
                $user->email_verified_at,
            ]
        ]);

        return Command::SUCCESS;
    }
}
