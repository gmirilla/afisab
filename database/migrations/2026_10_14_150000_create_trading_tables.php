<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 32); // grains, oil_seeds, tree_crops, cash_crops, other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16)->default('aggregator'); // farmer | aggregator | supplier
            $table->string('phone', 32)->nullable();
            $table->string('location')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16)->default('local'); // local | processor | export
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trade_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name');
            $table->foreignId('commodity_id')->constrained();
            $table->string('status', 16)->default('open'); // open | closed | cancelled
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            // Filled when the cycle closes
            $table->unsignedBigInteger('total_purchases')->default(0);
            $table->unsignedBigInteger('total_expenses')->default(0);
            $table->unsignedBigInteger('total_sales')->default(0);
            $table->bigInteger('net_profit')->nullable();
            $table->unsignedBigInteger('retained')->default(0);
            $table->timestamps();
        });

        // Which investor policies' capital a cycle uses (profit is shared pro rata to these amounts).
        Schema::create('cycle_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained();
            $table->unsignedBigInteger('amount'); // kobo
            $table->timestamps();

            $table->unique(['trade_cycle_id', 'investment_id']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('trade_cycle_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->unsignedBigInteger('quantity_kg');
            $table->unsignedBigInteger('unit_price'); // kobo per kg
            $table->unsignedBigInteger('total'); // kobo
            $table->string('status', 16)->default('proposed'); // proposed|approved|rejected|paid|received|cancelled
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['trade_cycle_id', 'status']);
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_cycle_id')->constrained();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('commodity_id')->constrained();
            $table->string('warehouse')->nullable();
            $table->unsignedBigInteger('quantity_kg'); // received
            $table->unsignedBigInteger('remaining_kg');
            $table->unsignedBigInteger('cost_total'); // kobo paid
            $table->unsignedBigInteger('remaining_cost');
            $table->string('grade', 32)->nullable();
            $table->decimal('moisture_percent', 4, 1)->nullable();
            $table->boolean('qa_passed')->default(true);
            $table->string('qa_notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('cycle_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_cycle_id')->constrained();
            $table->string('category', 24); // transport | storage | labour | packaging | other
            $table->string('description');
            $table->unsignedBigInteger('amount'); // kobo
            $table->string('status', 16)->default('proposed'); // proposed | paid | rejected
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('trade_cycle_id')->constrained();
            $table->foreignId('buyer_id')->constrained();
            $table->foreignId('inventory_lot_id')->constrained();
            $table->unsignedBigInteger('quantity_kg');
            $table->unsignedBigInteger('unit_price'); // kobo per kg
            $table->unsignedBigInteger('total'); // kobo
            $table->unsignedBigInteger('cost_of_goods')->default(0); // kobo, set when stock is reserved
            $table->string('status', 16)->default('draft'); // draft | confirmed | paid | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('payment_reference', 64)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['trade_cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['sales', 'cycle_expenses', 'inventory_lots', 'purchases', 'cycle_allocations', 'trade_cycles', 'buyers', 'suppliers', 'commodities'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
