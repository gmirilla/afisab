<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_carry_expected_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $accounts = User::factory()->create()->assignRole('accounts');
        $investor = User::factory()->create()->assignRole('investor');
        $admin = User::factory()->create()->assignRole('super-admin');

        $this->assertTrue($accounts->can('funds.view'));
        $this->assertFalse($accounts->can('staff.manage'));
        $this->assertFalse($investor->can('funds.view'));
        $this->assertTrue($admin->can('staff.manage'));
    }
}
