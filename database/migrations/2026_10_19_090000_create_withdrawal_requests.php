<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An investor's notice that they want their capital back before the term ends (agreement clause 9.2).
        // requested -> matured (policy matured early, payout process takes over) -> completed (capital paid)
        //          \-> cancelled
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained();
            $table->string('status', 12)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->date('notice_ends_on'); // requested date + the policy's notice period
            $table->text('reason')->nullable();
            $table->date('original_maturity_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_note')->nullable();
            $table->timestamp('matured_at')->nullable();
            $table->foreignId('matured_by')->nullable()->constrained('users')->nullOnDelete(); // null = the daily job
            $table->timestamp('completed_at')->nullable(); // capital paid
            $table->timestamps();

            $table->index(['status', 'notice_ends_on']);
            $table->index('investment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
