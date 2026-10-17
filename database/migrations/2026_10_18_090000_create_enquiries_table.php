<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages from the public website: quote requests from buyers, interest from suppliers, general questions.
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // quote | supplier | general
            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('commodity', 100)->nullable();
            $table->string('quantity', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('consented_at'); // agreed to us contacting them about the enquiry
            $table->string('status', 12)->default('new'); // new | contacted | closed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete(); // set when turned into a supplier record
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
