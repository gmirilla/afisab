<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable();
        });

        // Owner-configurable limits enforced when capital is allocated and purchases are approved.
        Schema::create('risk_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->unsignedBigInteger('value')->nullable(); // kobo, or basis points for ratios
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('takaful_policies', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('policy_number')->unique();
            $table->string('description');
            $table->unsignedBigInteger('premium'); // kobo
            $table->unsignedBigInteger('cover_amount'); // kobo
            $table->date('start_date');
            $table->date('end_date');
            $table->string('premium_status', 8)->default('unpaid'); // unpaid | paid
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_reference', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // set if the employee also has a login
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->unsignedBigInteger('basic_salary')->default(0); // kobo per month
            $table->string('bank_name')->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->string('tax_id', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One-off allowances, commissions and deductions for a given month.
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->string('type', 12); // allowance | commission | deduction
            $table->string('description');
            $table->unsignedBigInteger('amount'); // kobo
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'period']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();
            $table->string('status', 12)->default('draft'); // draft | approved | paid
            $table->unsignedBigInteger('total_gross')->default(0);
            $table->unsignedBigInteger('total_deductions')->default(0);
            $table->unsignedBigInteger('total_net')->default(0);
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedBigInteger('basic')->default(0);
            $table->unsignedBigInteger('allowances')->default(0);
            $table->unsignedBigInteger('commission')->default(0);
            $table->unsignedBigInteger('deductions')->default(0);
            $table->unsignedBigInteger('net')->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['payroll_lines', 'payroll_runs', 'payroll_adjustments', 'employees', 'takaful_policies', 'risk_limits'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('disabled_at');
        });
    }
};
