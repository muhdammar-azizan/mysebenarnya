<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
        });

        // Backfill staff accounts that already exist: use the part of the
        // email before the "@", made unique with a numeric suffix if needed.
        DB::table('users')
            ->whereIn('role', ['mcmc_staff', 'agency_staff'])
            ->whereNull('username')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(function ($user) {
                $base = strtolower(preg_replace('/[^a-z0-9.]/i', '', strstr($user->email, '@', true))) ?: 'staff';
                $username = $base;
                $i = 1;
                while (DB::table('users')->where('username', $username)->exists()) {
                    $username = $base.(++$i);
                }
                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
