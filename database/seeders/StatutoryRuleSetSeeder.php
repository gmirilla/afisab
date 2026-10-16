<?php

namespace Database\Seeders;

use App\Models\StatutoryRuleSet;
use Illuminate\Database\Seeder;

/**
 * Seeds ONE suggested rule set as a DRAFT. Nothing is calculated from it until the super-admin (with the
 * accountant) has checked every figure against current Nigerian law and activated it on the Statutory rates page.
 * Like the risk limits, it ships switched off on purpose.
 *
 * The figures are the developer's understanding of the Nigeria Tax Act 2025 bands, the Pension Reform Act minimums
 * (8% employee, 10% employer) and the NHF Act (2.5% of basic). They may be out of date: verify before activating.
 */
class StatutoryRuleSetSeeder extends Seeder
{
    public function run(): void
    {
        StatutoryRuleSet::firstOrCreate(['name' => 'Suggested rates (unverified draft)'], [
            'effective_from' => '2026-01-01',
            'status' => StatutoryRuleSet::DRAFT,
            'note' => 'Seeded suggestion. Confirm every band and rate with your accountant before activating.',
            'rules' => [
                'paye' => [
                    'bands' => [
                        ['width' => 80_000_000, 'rate_bps' => 0],        // first ₦800,000
                        ['width' => 220_000_000, 'rate_bps' => 1500],    // next ₦2,200,000
                        ['width' => 900_000_000, 'rate_bps' => 1800],    // next ₦9,000,000
                        ['width' => 1_300_000_000, 'rate_bps' => 2100],  // next ₦13,000,000
                        ['width' => 2_500_000_000, 'rate_bps' => 2300],  // next ₦25,000,000
                        ['width' => null, 'rate_bps' => 2500],           // the rest
                    ],
                    'rent_relief_bps' => 2000,
                    'rent_relief_cap' => 50_000_000, // ₦500,000 a year
                ],
                'pension' => ['employee_bps' => 800, 'employer_bps' => 1000],
                'nhf' => ['employee_bps' => 250],
                'remit' => ['paye_day' => 10, 'nhf_day' => 30, 'pension_working_days' => 7],
            ],
        ]);
    }
}
