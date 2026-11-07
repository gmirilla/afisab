<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An enquiry can be about several commodities, each with its own quantity. The single `commodity` and `quantity`
     * columns on `enquiries` stay as a summary (the first item) so old records and old code keep working.
     */
    public function up(): void
    {
        Schema::create('enquiry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->string('commodity', 100);
            $table->string('quantity', 100)->nullable();
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->index('commodity');
            $table->index(['enquiry_id', 'position']);
        });

        // Every existing enquiry that named a commodity becomes a one-item enquiry.
        DB::table('enquiries')->whereNotNull('commodity')->where('commodity', '!=', '')->orderBy('id')->each(function ($e) {
            DB::table('enquiry_items')->insert([
                'enquiry_id' => $e->id, 'commodity' => $e->commodity, 'quantity' => $e->quantity, 'position' => 1,
                'created_at' => $e->created_at, 'updated_at' => $e->created_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_items');
    }
};
