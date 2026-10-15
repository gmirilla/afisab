<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

#[Signature('afisab:make-staff {name} {email} {role=super-admin}')]
#[Description('Create a verified staff user with a role (prompts for the password)')]
class MakeStaffUser extends Command
{
    public function handle(): int
    {
        $role = Role::where('name', $this->argument('role'))->where('name', '!=', 'investor')->first();
        if (! $role) {
            $this->error('Unknown staff role. Run `php artisan db:seed` first, or choose one of: '
                .Role::where('name', '!=', 'investor')->pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $password = $this->secret('Password (min 10 chars, mixed case + number)');
        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['email', 'unique:users,email'], 'password' => [Password::min(10)->mixedCase()->numbers()]],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::create(['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->info("Created {$user->email} as {$role->name}. Enable two-factor authentication after first sign-in.");

        return self::SUCCESS;
    }
}
