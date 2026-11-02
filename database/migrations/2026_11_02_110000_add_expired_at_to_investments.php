<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Set when an unpaid subscription request lapses (cancelled by the system, not by the investor). */
    public function up(): void
    {
        Schema::table('investments', fn (Blueprint $table) => $table->timestamp('expired_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('investments', fn (Blueprint $table) => $table->dropColumn('expired_at'));
    }
};
