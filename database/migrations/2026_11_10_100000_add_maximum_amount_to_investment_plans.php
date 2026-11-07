<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The most one subscription to a plan may be, in kobo. Empty means no maximum, so existing plans are unchanged. */
    public function up(): void
    {
        Schema::table('investment_plans', fn (Blueprint $table) => $table->unsignedBigInteger('maximum_amount')->nullable());
    }

    public function down(): void
    {
        Schema::table('investment_plans', fn (Blueprint $table) => $table->dropColumn('maximum_amount'));
    }
};
