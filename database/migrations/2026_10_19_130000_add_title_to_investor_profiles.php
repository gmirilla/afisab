<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the investor is addressed on receipts and the agreement (Mr., Mrs., Alh., Dr.). Optional.
        Schema::table('investor_profiles', fn (Blueprint $t) => $t->string('title', 12)->nullable()->after('user_id'));
    }

    public function down(): void
    {
        Schema::table('investor_profiles', fn (Blueprint $t) => $t->dropColumn('title'));
    }
};
