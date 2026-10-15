<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->string('certificate_token', 32)->nullable()->unique()->after('certificate_number');
            $table->timestamp('maturity_reminder_sent_at')->nullable();
        });

        // One row per policy per trading cycle: the policy's pro-rata share of the cycle result.
        // Investor profit is NOT decided here; it is decided in the monthly distribution run.
        Schema::create('performance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('cycle_reference', 64);
            $table->unsignedBigInteger('capital_allocated'); // kobo
            $table->bigInteger('net_profit'); // kobo, negative = loss
            $table->unsignedBigInteger('distribution_run_id')->nullable()->index(); // set when swept into a run
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['investment_id', 'cycle_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_entries');
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['certificate_token', 'maturity_reminder_sent_at']);
        });
    }
};
