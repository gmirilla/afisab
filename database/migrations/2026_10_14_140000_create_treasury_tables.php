<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Return of principal to an investor at maturity: prepared -> approved -> paid (or rejected).
        Schema::create('capital_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained();
            $table->unsignedBigInteger('amount'); // kobo
            $table->string('status', 16)->default('prepared');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['investment_id', 'status']);
        });

        // A point-in-time check of the ledger against the real bank statement.
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained();
            $table->date('as_of');
            $table->bigInteger('statement_balance'); // kobo, from the bank
            $table->bigInteger('ledger_balance'); // kobo, as at end of as_of
            $table->bigInteger('difference'); // statement - ledger
            $table->string('note')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ledger_account_id', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('capital_payouts');
    }
};
