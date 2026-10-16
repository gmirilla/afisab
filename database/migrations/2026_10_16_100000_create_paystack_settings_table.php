<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row. Keys are stored encrypted (APP_KEY); when there is no row the PAYSTACK_* .env values are used.
        Schema::create('paystack_settings', function (Blueprint $table) {
            $table->id();
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_settings');
    }
};
