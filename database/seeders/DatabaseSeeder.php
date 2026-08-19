<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $email = config('gym.admin.email');
        $password = config('gym.admin.password');

        if (blank($email) || blank($password)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('gym.admin.name'),
                'password' => $password,
                'role' => UserRole::Admin,
            ],
        );
    }
}
