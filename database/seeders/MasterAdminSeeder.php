<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('MASTER_ADMIN_EMAIL', 'admin@gmail.com');
        $pass  = env('MASTER_ADMIN_PASSWORD', 'Admin123');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => 'Master Admin',
                'password'          => Hash::make($pass),
                'role'              => User::ROLE_ADMIN,   // ensure users.role column exists
                'email_verified_at' => now(),
            ]
        );
    }
}
