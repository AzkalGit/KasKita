<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        // Owner dibuat lewat seeder, bukan lewat halaman daftar
        User::create([
            'name' => 'Owner KasKita',
            'email' => 'owner@kaskita.demo',
            'password' => 'KitaDemo2026!', // di-hash otomatis oleh cast 'hashed'
            'platform_role' => 'owner',
        ]);
    }
}