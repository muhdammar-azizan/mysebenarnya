<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $moh = Agency::where('code', 'MOH')->first();
        $bnm = Agency::where('code', 'BNM')->first();
        $spr = Agency::where('code', 'SPR')->first();

        User::create([
            'name' => 'Ahmad Faiz (MCMC)',
            'email' => 'mcmc.admin@sebenarnya.my',
            'password' => Hash::make('password'),
            'role' => UserRole::McmcStaff,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Siti Nurhaliza (MCMC)',
            'email' => 'mcmc.staff@sebenarnya.my',
            'password' => Hash::make('password'),
            'role' => UserRole::McmcStaff,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Dr. Kamal Hassan',
            'email' => 'agency.moh@sebenarnya.my',
            'password' => Hash::make('password'),
            'role' => UserRole::AgencyStaff,
            'agency_id' => $moh->id,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Lim Wei Ling',
            'email' => 'agency.bnm@sebenarnya.my',
            'password' => Hash::make('password'),
            'role' => UserRole::AgencyStaff,
            'agency_id' => $bnm->id,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Nurul Izzah',
            'email' => 'agency.spr@sebenarnya.my',
            'password' => Hash::make('password'),
            'role' => UserRole::AgencyStaff,
            'agency_id' => $spr->id,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);

        $publicUsers = [
            ['name' => 'Tan Mei Ling', 'email' => 'public.tan@example.com'],
            ['name' => 'Muhammad Ali', 'email' => 'public.ali@example.com'],
            ['name' => 'Priya Devi', 'email' => 'public.priya@example.com'],
            ['name' => 'Aiman Yusof', 'email' => 'public.aiman@example.com'],
        ];

        foreach ($publicUsers as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('password'),
                'role' => UserRole::Public,
                'email_verified_at' => now(),
            ]);
        }
    }
}
