<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A new cycle is proposed (status 'pending') and a Director approves it before it can trade.
        Schema::table('trade_cycles', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('decision_note')->nullable();
        });

        // Cycles that already exist were opened under the old rules: treat them as approved.
        DB::table('trade_cycles')->whereIn('status', ['open', 'closed'])->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('trade_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'decision_note']);
        });
    }
};
