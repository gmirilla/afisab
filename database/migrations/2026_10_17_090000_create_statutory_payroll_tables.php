<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pension fund administrators. Staff each have their own, so remittances are grouped per PFA.
        Schema::create('pension_administrators', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('bank_name')->nullable();
            $table->string('account_number', 10)->nullable();
            $table->string('account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Versioned PAYE / pension / NHF rules. draft -> active (locked for good). A month uses the active set
        // with the latest effective_from on or before that month; a new law is a new set, never an edit.
        Schema::create('statutory_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_from');
            $table->string('status', 8)->default('draft'); // draft | active
            $table->json('rules');
            $table->text('note')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'effective_from']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('housing_allowance')->default(0); // kobo per month; pensionable
            $table->unsignedBigInteger('transport_allowance')->default(0); // kobo per month; pensionable
            $table->foreignId('pension_administrator_id')->nullable()->constrained('pension_administrators')->nullOnDelete();
            $table->string('rsa_pin', 20)->nullable();
            $table->boolean('pension_exempt')->default(false);
            $table->boolean('nhf_applies')->default(true);
            $table->string('nhf_number', 32)->nullable();
            $table->boolean('paye_exempt')->default(false);
            $table->unsignedBigInteger('annual_rent')->default(0); // kobo per year, for rent relief
            $table->string('tax_state', 60)->nullable(); // state internal revenue service PAYE is remitted to
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('statutory_rule_set_id')->nullable()->constrained('statutory_rule_sets')->nullOnDelete();
            $table->unsignedBigInteger('total_paye')->default(0);
            $table->unsignedBigInteger('total_pension_employee')->default(0);
            $table->unsignedBigInteger('total_pension_employer')->default(0);
            $table->unsignedBigInteger('total_nhf')->default(0);
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('housing')->default(0);
            $table->unsignedBigInteger('transport')->default(0);
            $table->unsignedBigInteger('pensionable')->default(0); // the pension base for the month
            $table->unsignedBigInteger('pension_employee')->default(0);
            $table->unsignedBigInteger('pension_employer')->default(0);
            $table->unsignedBigInteger('nhf')->default(0);
            $table->unsignedBigInteger('taxable')->default(0); // monthly pay after pension and NHF
            $table->unsignedBigInteger('paye')->default(0);
            $table->unsignedTinyInteger('tax_months')->default(0); // months of the tax year counted for the year-to-date method
            $table->foreignId('pension_administrator_id')->nullable()->constrained('pension_administrators')->nullOnDelete();
        });

        // What is owed to each authority after a payroll is paid, until Accounts remits it.
        Schema::create('statutory_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained();
            $table->string('kind', 8); // paye | pension | nhf
            $table->foreignId('pension_administrator_id')->nullable()->constrained('pension_administrators')->nullOnDelete();
            $table->string('payee'); // "Lagos", "Stanbic IBTC Pension", "FMBN" — what the schedule is addressed to
            $table->unsignedBigInteger('amount'); // kobo
            $table->date('due_date');
            $table->string('status', 8)->default('due'); // due | remitted
            $table->foreignId('remitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remitted_at')->nullable();
            $table->string('reference', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_remittances');
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pension_administrator_id');
            $table->dropColumn(['housing', 'transport', 'pensionable', 'pension_employee', 'pension_employer', 'nhf', 'taxable', 'paye', 'tax_months']);
        });
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('statutory_rule_set_id');
            $table->dropColumn(['total_paye', 'total_pension_employee', 'total_pension_employer', 'total_nhf']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pension_administrator_id');
            $table->dropColumn(['housing_allowance', 'transport_allowance', 'rsa_pin', 'pension_exempt', 'nhf_applies', 'nhf_number', 'paye_exempt', 'annual_rent', 'tax_state']);
        });
        Schema::dropIfExists('statutory_rule_sets');
        Schema::dropIfExists('pension_administrators');
    }
};
