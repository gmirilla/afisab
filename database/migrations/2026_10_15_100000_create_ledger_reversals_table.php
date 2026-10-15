<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A request to correct the ledger: requested -> approved (mirror entry posted) or rejected.
        Schema::create('ledger_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained();
            $table->string('status', 16)->default('requested');
            $table->string('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['journal_entry_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reversals');
    }
};
