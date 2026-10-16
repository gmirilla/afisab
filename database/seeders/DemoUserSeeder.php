<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development/staging sign-ins: a Director, a back-office admin (Accounts) and an investor.
 *
 *   php artisan db:seed --class=DemoUserSeeder
 *
 * Deliberately NOT part of DatabaseSeeder and refuses to run in production (real staff are created
 * with `php artisan afisab:make-staff`). Password comes from DEMO_USER_PASSWORD, default "Password123!".
 * Run RolesAndPermissionsSeeder first. Staff still have to set up two-factor when it is enforced.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoUserSeeder refuses to run in production. Use afisab:make-staff.');

            return;
        }

        $password = Hash::make(env('DEMO_USER_PASSWORD', 'Password123!'));

        $users = [
            ['Super Admin', 'superadmin@afisab.test', 'super-admin'],
            ['Admin (Accounts)', 'admin@afisab.test', 'accounts'],
            ['Demo Investor', 'investor@afisab.test', 'investor'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => $password]);
            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$role]);

            if ($role === 'investor') {
                $user->investorProfile()->firstOrCreate([], [
                    'phone' => '08000000000',
                    'kyc_status' => 'approved',
                ]);
            }
        }

        $this->command?->info('Demo users ready: superadmin@afisab.test, admin@afisab.test, investor@afisab.test');
    }
}
