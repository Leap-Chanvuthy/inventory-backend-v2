<?php

namespace App\Console\Commands;

use App\Models\Role;
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

        $adminRole = Role::query()->where('key', 'ADMIN')->first();
        if (!$adminRole) {
            $this->error('❌ ADMIN role not found. Please run role seeders first.');
            return Command::FAILURE;
        }

        $user = User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->info('✅ Admin user created successfully!');
        $this->table(['ID', 'Name', 'Email', 'Role', 'Email Verified At'], [
            [
                $user->id,
                $user->name,
                $user->email,
                $user->role?->key,
                $user->email_verified_at,
            ]
        ]);

        return Command::SUCCESS;
    }
}
