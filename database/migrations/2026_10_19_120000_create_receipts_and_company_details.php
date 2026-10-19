<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The day the money actually arrived, which for a bank transfer can be earlier than the day staff confirmed it.
        Schema::table('payments', function (Blueprint $table) {
            $table->date('received_on')->nullable();
        });

        // The company's address and extra contact lines, as printed on receipts. One row, edited by the super-admin.
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();
            $table->text('address')->nullable();
            $table->string('phone_secondary', 32)->nullable();
            $table->string('investment_email', 150)->nullable();
            $table->string('website', 150)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The company stamp image. Kept like signatures: one active, old ones retained so every receipt says which it used.
        Schema::create('company_stamps', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One receipt per confirmed payment, as the exact file issued (a correction is a new version).
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->string('receipt_number', 40); // AFISAB/INV/2026/0001, the same across versions
            $table->unsignedSmallInteger('version');
            $table->string('status', 12)->default('issued'); // issued | superseded | void
            $table->unsignedBigInteger('amount'); // kobo, as receipted
            $table->date('received_on');
            $table->string('file_path');
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->foreignId('signatory_id')->nullable()->constrained('signatories')->nullOnDelete();
            $table->foreignId('stamp_id')->nullable()->constrained('company_stamps')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->string('reason')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['receipt_number', 'version']);
            $table->unique(['payment_id', 'version']);
            $table->index('investment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('company_stamps');
        Schema::dropIfExists('company_details');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('received_on'));
    }
};
