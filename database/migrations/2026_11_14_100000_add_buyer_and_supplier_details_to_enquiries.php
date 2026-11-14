<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What a buyer or supplier tells us on the website forms beyond the basics. All optional: older enquiries and the general form do not have them. */
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('country', 80)->nullable();
            $table->string('buyer_type', 24)->nullable();
            $table->string('destination', 120)->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('preferred_grade', 100)->nullable();
            $table->string('packaging', 120)->nullable();
            $table->text('quality_requirements')->nullable();
            $table->string('readiness', 16)->nullable();
            $table->date('available_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $table) => $table->dropColumn(['country', 'buyer_type', 'destination', 'delivery_date', 'preferred_grade', 'packaging', 'quality_requirements', 'readiness', 'available_from']));
    }
};
