<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedBigInteger('minimum_amount'); // kobo
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->text('bvn')->nullable(); // encrypted via model cast
            $table->string('bank_name')->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->string('id_document_path')->nullable();
            $table->string('kyc_status', 16)->default('incomplete');
            $table->text('kyc_note')->nullable();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_plan_id')->constrained();
            $table->unsignedBigInteger('amount'); // kobo
            $table->unsignedSmallInteger('duration_months');
            $table->string('status', 24)->default('pending_payment');
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('certificate_number', 32)->nullable()->unique();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->string('method', 16); // paystack | transfer
            $table->unsignedBigInteger('amount'); // kobo
            $table->string('status', 24)->default('pending');
            $table->string('reference', 64)->unique();
            $table->string('gateway_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('investor_profiles');
        Schema::dropIfExists('investment_plans');
    }
};
