<?php

namespace Database\Seeders;

use App\Models\InvestmentPlan;
use Illuminate\Database\Seeder;

class InvestmentPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['3-Month Plan', '3-month', 3, 'Short-cycle commodity trading participation.'],
            ['6-Month Plan', '6-month', 6, 'Mid-term participation across one or two trading cycles.'],
            ['12-Month Plan', '12-month', 12, 'Full-year participation across multiple trading cycles.'],
        ];

        foreach ($plans as $i => [$name, $slug, $months, $description]) {
            InvestmentPlan::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'duration_months' => $months,
                'minimum_amount' => config('afisab.minimum_investment'),
                'description' => $description,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
