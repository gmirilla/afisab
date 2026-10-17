<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // monthly | quarterly | at_maturity. Existing policies were all paid monthly, so that stays their value.
        Schema::table('investments', function (Blueprint $table) {
            $table->string('profit_frequency', 12)->default('monthly');
        });

        // What each distribution line covers, so a quarterly or one-off payment can say "profit for Jul to Sep".
        Schema::table('distribution_lines', function (Blueprint $table) {
            $table->string('frequency', 12)->nullable();
            $table->date('covers_from')->nullable();
            $table->date('covers_to')->nullable();
        });

        Schema::table('distribution_runs', function (Blueprint $table) {
            $table->unsignedInteger('policies_deferred')->default(0); // policies with results not yet due this month
        });
    }

    public function down(): void
    {
        Schema::table('distribution_runs', fn (Blueprint $t) => $t->dropColumn('policies_deferred'));
        Schema::table('distribution_lines', fn (Blueprint $t) => $t->dropColumn(['frequency', 'covers_from', 'covers_to']));
        Schema::table('investments', fn (Blueprint $t) => $t->dropColumn('profit_frequency'));
    }
};
