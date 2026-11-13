<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo presentasi: 3 akun utama (Furqan, Fandi, Fadel) dan 4 organisasi.
 * Akun Owner dibuat di OwnerSeeder. Akun tambahan: php artisan kaskita:isi-demo.
 * PERINGATAN: kata sandi lemah, HANYA untuk demo lokal.
 */
class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $akun = [
                'furqan' => User::forceCreate(['name' => 'M. Dwi Furqan', 'email' => 'furqan@gmail.com', 'password' => 'furqan123', 'email_verified_at' => now()]),
                'fandi' => User::forceCreate(['name' => 'Raifandi Afriza', 'email' => 'fandi@gmail.com', 'password' => 'fandi123', 'email_verified_at' => now()]),
                'fadel' => User::forceCreate(['name' => 'M. Fadel Fadlurrahman Syabri', 'email' => 'fadel@gmail.com', 'password' => 'fadel123', 'email_verified_at' => now()]),
            ];

            $grup = [
                [
                    'name' => 'HMMI',
                    'opening' => 1000000, 'dues' => 20000, 'code' => 'HMMI2026',
                    'info' => 'Transfer ke rekening fiktif 1111222233 a.n. HMMI',
                    'peran' => ['furqan' => 'admin', 'fandi' => 'editor', 'fadel' => 'anggota'],
                    'kategori' => ['Donasi', 'Konsumsi', 'Perlengkapan', 'Transportasi'],
                    'transaksi' => [
                        ['Donasi', 'masuk', 300000, '2026-10-04', 'Donasi alumni untuk kegiatan himpunan'],
                        ['Konsumsi', 'keluar', 175000, '2026-10-12', 'Konsumsi rapat kerja'],
                        ['Perlengkapan', 'keluar', 90000, '2026-10-18', 'Spanduk dan alat tulis'],
                        ['Transportasi', 'keluar', 60000, '2026-11-05', 'Transport kunjungan studi'],
                    ],
                ],
                [
                    'name' => 'HMMI Divisi Kewirausahaan',
                    'opening' => 750000, 'dues' => 15000, 'code' => 'HMMIKWU6',
                    'info' => 'Transfer ke rekening fiktif 2222333344 a.n. HMMI Divisi Kewirausahaan',
                    'peran' => ['fandi' => 'admin', 'fadel' => 'editor', 'furqan' => 'anggota'],
                    'kategori' => ['Penjualan', 'Bahan Produksi', 'Promosi', 'Kemasan'],
                    'transaksi' => [
                        ['Penjualan', 'masuk', 450000, '2026-10-07', 'Hasil penjualan bazar kampus'],
                        ['Bahan Produksi', 'keluar', 220000, '2026-10-09', 'Bahan baku produk bazar'],
                        ['Promosi', 'keluar', 50000, '2026-10-16', 'Cetak brosur dan stiker'],
                        ['Kemasan', 'keluar', 40000, '2026-11-03', 'Kemasan produk'],
                    ],
                ],
                [
                    'name' => 'BEM',
                    'opening' => 2000000, 'dues' => 25000, 'code' => 'BEM26KAS',
                    'info' => 'Transfer ke rekening fiktif 3333444455 a.n. BEM',
                    'peran' => ['fadel' => 'admin', 'furqan' => 'editor', 'fandi' => 'anggota'],
                    'kategori' => ['Donasi', 'Konsumsi', 'Perlengkapan', 'Kegiatan'],
                    'transaksi' => [
                        ['Donasi', 'masuk', 500000, '2026-10-02', 'Dana dukungan kegiatan dari fakultas'],
                        ['Kegiatan', 'keluar', 600000, '2026-10-14', 'Penyelenggaraan seminar'],
                        ['Konsumsi', 'keluar', 250000, '2026-10-22', 'Konsumsi rapat pleno'],
                        ['Perlengkapan', 'keluar', 120000, '2026-11-06', 'Perlengkapan sekretariat'],
                    ],
                ],
                [
                    'name' => 'Komunitas Freelancer Digital Nusantara',
                    'opening' => 1500000, 'dues' => 30000, 'code' => 'FRLN2026',
                    'info' => 'Transfer ke rekening fiktif 4444555566 a.n. Komunitas Freelancer Digital Nusantara',
                    'peran' => ['furqan' => 'admin', 'fadel' => 'editor', 'fandi' => 'anggota'],
                    'kategori' => ['Sponsor', 'Sewa Tempat', 'Acara Kopdar', 'Langganan Alat'],
                    'transaksi' => [
                        ['Sponsor', 'masuk', 800000, '2026-10-05', 'Sponsor acara kopdar bulanan'],
                        ['Sewa Tempat', 'keluar', 350000, '2026-10-10', 'Sewa ruang co-working'],
                        ['Acara Kopdar', 'keluar', 200000, '2026-10-24', 'Konsumsi dan cetak sertifikat kopdar'],
                        ['Langganan Alat', 'keluar', 130000, '2026-11-04', 'Langganan alat desain bersama'],
                    ],
                ],
            ];

            foreach ($grup as $g) {
                $this->buatGrup($g, $akun);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $g
     * @param  array<string, User>  $akun
     */
    private function buatGrup(array $g, array $akun): void
    {
        // Admin pertama menjadi pembuat organisasi
        $kunciAdmin = array_search('admin', $g['peran'], true);
        $admin = $akun[$kunciAdmin];

        $org = Organization::create([
            'name' => $g['name'],
            'status' => 'aktif',
            'opening_balance' => $g['opening'],
            'default_dues' => $g['dues'],
            'payment_info' => $g['info'],
            'created_by' => $admin->id,
        ]);

        // Admin dan Editor tidak ditagih iuran; Anggota ditagih (D3/D12)
        $pelaku = []; // peran => User, untuk "dicatat oleh"
        foreach ($g['peran'] as $kunci => $peran) {
            $user = $akun[$kunci];
            $anggota = $org->members()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $peran,
                'is_billable' => $peran === 'anggota',
            ]);
            $pelaku[$peran] = $user;

            if ($peran === 'anggota') {
                // Tagihan bulan ini belum dibayar, supaya alur pembayaran bisa didemokan
                Invoice::create([
                    'member_id' => $anggota->id, 'period' => '2026-11',
                    'amount' => $g['dues'], 'status' => 'belum',
                ]);
            }
        }

        $kategori = ['Iuran' => Category::create(['organization_id' => $org->id, 'name' => 'Iuran', 'is_system' => true])];
        foreach ($g['kategori'] as $nama) {
            $kategori[$nama] = Category::create(['organization_id' => $org->id, 'name' => $nama]);
        }

        foreach ($g['transaksi'] as [$kat, $tipe, $jumlah, $tanggal, $ket]) {
            Transaction::create([
                'organization_id' => $org->id,
                'category_id' => $kategori[$kat]->id,
                'created_by' => $tipe === 'masuk' ? $admin->id : ($pelaku['editor'] ?? $admin)->id,
                'type' => $tipe,
                'amount' => $jumlah,
                'tanggal' => $tanggal,
                'description' => $ket,
            ]);
        }

        Invitation::create([
            'organization_id' => $org->id,
            'code' => $g['code'],
            'created_by' => $admin->id,
        ]);
    }
}
