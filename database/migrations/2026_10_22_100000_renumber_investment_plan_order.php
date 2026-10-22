<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The order of the plan cards is now managed from the back office: give every plan a unique position 1..n, keeping today's order. */
    public function up(): void
    {
        DB::table('investment_plans')->orderBy('sort_order')->orderBy('id')->pluck('id')->each(
            fn ($id, $i) => DB::table('investment_plans')->where('id', $id)->update(['sort_order' => $i + 1])
        );
    }

    public function down(): void
    {
        // positions are harmless to keep
    }
};
