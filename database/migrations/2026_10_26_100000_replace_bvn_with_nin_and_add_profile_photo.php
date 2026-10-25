<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company asks for the National Identification Number (NIN) instead of the BVN. A BVN cannot be turned into a
     * NIN, so the stored BVN values are deleted (the column is dropped) and every investor adds their NIN. `nin` is
     * encrypted like the BVN was; `nin_hash` is a keyed one-way hash used only to stop one NIN being registered on two
     * accounts. `photo_path` is the investor's profile picture (private disk).
     */
    public function up(): void
    {
        Schema::table('investor_profiles', function (Blueprint $table) {
            $table->text('nin')->nullable();
            $table->char('nin_hash', 64)->nullable()->unique();
            $table->string('photo_path')->nullable();
        });
        Schema::table('investor_profiles', fn (Blueprint $table) => $table->dropColumn('bvn'));
    }

    public function down(): void
    {
        Schema::table('investor_profiles', fn (Blueprint $table) => $table->text('bvn')->nullable()); // the deleted values are not recoverable
        Schema::table('investor_profiles', function (Blueprint $table) {
            $table->dropUnique(['nin_hash']);
            $table->dropColumn(['nin', 'nin_hash', 'photo_path']);
        });
    }
};
