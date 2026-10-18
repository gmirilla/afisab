<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The terms the investor accepted, frozen when they subscribed, plus proof of acceptance.
        Schema::table('investments', function (Blueprint $table) {
            $table->json('terms')->nullable();
            $table->string('policy_number', 40)->nullable()->unique(); // MIC/10-2026/OYO/0000001, set when the agreement is first issued
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_ip', 45)->nullable();
        });

        // Details the agreement's schedule asks for.
        Schema::table('investor_profiles', function (Blueprint $table) {
            $table->string('occupation', 120)->nullable();
            $table->string('occupation_address', 255)->nullable();
            $table->string('nominee_name', 150)->nullable();
            $table->string('nominee_relationship', 80)->nullable();
            $table->string('nominee_phone', 32)->nullable();
        });

        // Who signs for the company. One is active; old ones are kept so every issued document can say who signed it.
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('title', 150);
            $table->string('signature_path'); // private disk, never served by URL to the public
            $table->boolean('is_active')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Every issued agreement, kept as the exact file that was issued (reprints return it unchanged).
        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 12)->default('issued'); // issued | superseded
            $table->string('template_version', 12);
            $table->string('policy_number', 40);
            $table->string('file_path');
            $table->char('sha256', 64);
            $table->unsignedInteger('size');
            $table->foreignId('signatory_id')->nullable()->constrained('signatories')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->string('reason')->nullable(); // why a new version was issued
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['investment_id', 'version']);
        });

        // Gap-free counters (policy numbers).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('name', 40)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('policy_documents');
        Schema::dropIfExists('signatories');
        Schema::table('investor_profiles', fn (Blueprint $t) => $t->dropColumn(['occupation', 'occupation_address', 'nominee_name', 'nominee_relationship', 'nominee_phone']));
        Schema::table('investments', function (Blueprint $t) {
            $t->dropUnique(['policy_number']);
            $t->dropColumn(['terms', 'policy_number', 'terms_accepted_at', 'terms_accepted_ip']);
        });
    }
};
