<?php

namespace Database\Seeders;

use App\Models\RiskLimit;
use App\Services\Risk\RiskService;
use Illuminate\Database\Seeder;

/**
 * Creates every limit as INACTIVE with a suggested value. Nothing is enforced until the owner reviews the
 * figures on the Risk page and switches the limits on (DECISIONS.md Q13, Q14).
 */
class RiskLimitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RiskService::LIMITS as $key => $definition) {
            RiskLimit::firstOrCreate(['key' => $key], ['value' => $definition['suggested'], 'is_active' => false]);
        }
    }
}
