<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-factor authentication is now required (or not) per staff member, switched by the super-admin. Existing staff keep
     * exactly what applied to them until now: required where AFISAB_REQUIRE_2FA was on (the production default), not
     * required where it was off. New staff accounts are created with it ON (see UserController and afisab:make-staff).
     */
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('two_factor_required')->default(false));

        $enforcedUntilNow = filter_var(env('AFISAB_REQUIRE_2FA', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN);
        if ($enforcedUntilNow) {
            $staffIds = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', '!=', 'investor')->pluck('model_has_roles.model_id');
            DB::table('users')->whereIn('id', $staffIds)->update(['two_factor_required' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('two_factor_required'));
    }
};
