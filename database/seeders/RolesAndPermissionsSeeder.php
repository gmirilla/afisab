<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, list<string>> role => permissions ('*' = all) */
    private const ROLES = [
        'super-admin' => ['*'],
        'accounts' => ['policies.send', 'funds.view', 'ledger.view', 'payments.confirm', 'payouts.prepare', 'reconciliation.manage', 'reversals.request', 'reports.view', 'investors.view', 'purchases.approve', 'purchases.pay', 'sales.receive', 'trading.view', 'risk.view', 'takaful.manage', 'payroll.pay'],
        'sales' => ['enquiries.manage', 'cycles.manage', 'sales.manage', 'purchases.propose', 'buyers.manage', 'trading.view', 'funds.view', 'investors.view'],
        'procurement' => ['purchases.propose', 'inventory.manage', 'suppliers.manage', 'trading.view'],
        'hr' => ['staff.manage', 'payroll.manage'],
        'compliance' => ['policies.send', 'policies.issue', 'kyc.review', 'investors.view', 'certificates.issue'],
        'auditor' => ['funds.view', 'ledger.view', 'reports.view', 'audit.view', 'investors.view', 'trading.view', 'risk.view'],
        'investor' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::ROLES)->flatten()->reject(fn ($p) => $p === '*')
            ->merge(['plans.manage', 'users.manage', 'payouts.approve', 'risk.manage', 'payroll.approve', 'reversals.approve', 'bank.manage', 'paystack.manage', 'statutory.manage', 'cycles.approve', 'signatory.manage', 'queue.manage'])->unique();
        $all->each(fn ($name) => Permission::findOrCreate($name, 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions(); // permissions were cached empty on a fresh DB

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions === ['*'] ? $all->all() : $permissions);
        }
    }
}
