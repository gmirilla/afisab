<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_three_users_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $this->assertSame(3, User::count());
        $this->assertTrue(User::where('email', 'superadmin@afisab.test')->first()->hasRole('super-admin'));
        $this->assertTrue(User::where('email', 'admin@afisab.test')->first()->hasRole('accounts'));
        $investor = User::where('email', 'investor@afisab.test')->first();
        $this->assertTrue($investor->hasRole('investor'));
        $this->assertSame('approved', $investor->investorProfile->kyc_status->value ?? $investor->investorProfile->kyc_status);
        $this->assertTrue(Hash::check('Password123!', $investor->password));
        $this->assertNotNull($investor->email_verified_at);
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->app['env'] = 'production';

        app(DemoUserSeeder::class)->run();

        $this->assertSame(0, User::count());
    }
}
