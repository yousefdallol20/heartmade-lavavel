<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_LOGIN_EMAIL');
        $password = env('ADMIN_LOGIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException(
                'ADMIN_LOGIN_EMAIL and ADMIN_LOGIN_PASSWORD are required.'
            );
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = 'Heart Made Admin';
            $user->password = $password;
            $user->email_verified_at = now();
        }

        $user->is_admin = true;
        $user->save();
    }
}
