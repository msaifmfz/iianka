<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User;
        $user->forceFill([
            'name' => 'E2E Login User',
            'login_id' => 'e2e-login',
            'email' => 'e2e-login@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => UserRole::Viewer,
            'is_admin' => false,
            'is_hidden_from_workers' => false,
        ])->save();
    }
}
