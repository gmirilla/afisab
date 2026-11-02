<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The ledger is naira-only, so the foreign-currency account's balance is a naira value: say so instead of implying dollars. */
    public function up(): void
    {
        DB::table('ledger_accounts')->where('code', 'DOMICILIARY')->update([
            'name' => 'Domiciliary Account (naira value)',
            'description' => 'Export proceeds and foreign-currency payments, recorded at their naira value. The ledger keeps naira only; foreign-currency balances and exchange gains are not built (DECISIONS Q15).',
        ]);
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->where('code', 'DOMICILIARY')->update(['name' => 'Domiciliary Account (USD/GBP/EUR)', 'description' => 'Export proceeds and foreign-currency payments.']);
    }
};
