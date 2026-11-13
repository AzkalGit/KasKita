<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        // Owner platform dibuat lewat seeder, bukan lewat halaman daftar (D17).
        // forceCreate: platform_role dan email_verified_at sengaja tidak mass-assignable.
        // PERINGATAN: kata sandi lemah ini HANYA untuk demo lokal.
        User::forceCreate([
            'name' => 'M. Azkal Azkia',
            'email' => 'azkal@gmail.com',
            'password' => 'azkal123', // di-hash otomatis oleh cast 'hashed'
            'email_verified_at' => now(),
            'platform_role' => 'owner',
        ]);
    }
}
