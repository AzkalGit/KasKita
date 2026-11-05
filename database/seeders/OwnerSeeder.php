<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        // Owner dibuat lewat seeder, bukan lewat halaman daftar
        // forceCreate: platform_role dan email_verified_at sengaja tidak mass-assignable
        User::forceCreate([
            'name' => 'Owner KasKita',
            'email' => 'owner@kaskita.demo',
            'password' => 'KitaDemo2026!', // di-hash otomatis oleh cast 'hashed'
            'email_verified_at' => now(),
            'platform_role' => 'owner',
        ]);
    }
}
