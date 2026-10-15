<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // YYYY-MM
            $table->date('cutoff_date'); // results recorded up to the end of this day are included
            $table->date('pay_by'); // last day of the month
            $table->string('status', 16)->default('calculated'); // calculated | approved | paid
            $table->bigInteger('total_net_profit')->default(0);
            $table->unsignedBigInteger('total_retained')->default(0);
            $table->unsignedBigInteger('total_investor')->default(0);
            $table->unsignedBigInteger('total_company')->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamps();
        });

        Schema::create('distribution_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained();
            $table->bigInteger('net_profit'); // monthly net across the policy's cycle results (kobo, may be negative)
            $table->unsignedBigInteger('retained')->default(0);
            $table->bigInteger('distributable')->default(0);
            $table->unsignedBigInteger('investor_share')->default(0);
            $table->unsignedBigInteger('company_share')->default(0);
            $table->string('status', 16)->default('pending'); // pending | payable | paid | none
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['distribution_run_id', 'investment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_lines');
        Schema::dropIfExists('distribution_runs');
    }
};
