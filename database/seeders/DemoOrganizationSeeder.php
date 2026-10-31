<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrganizationSeeder extends Seeder
{
    private const PASSWORD = 'KitaDemo2026!';

    public function run(): void
    {
        DB::transaction(function () {
            // --- Akun demo ---
            $nadia = User::create(['name' => 'Nadia Putri', 'email' => 'admin@kaskita.demo', 'password' => self::PASSWORD]);
            $rafi = User::create(['name' => 'Rafi Pratama', 'email' => 'editor@kaskita.demo', 'password' => self::PASSWORD]);
            $dimas = User::create(['name' => 'Dimas Saputra', 'email' => 'anggota@kaskita.demo', 'password' => self::PASSWORD]);

            // --- Organisasi ---
            $org = Organization::create([
                'name' => 'BEM Fakultas Teknologi',
                'status' => 'aktif',
                'opening_balance' => 500000,
                'default_dues' => 25000,
                'payment_info' => 'Transfer ke rekening fiktif 1234567890 a.n. BEM Fakultas Teknologi',
                'created_by' => $nadia->id,
            ]);

            // --- Anggota yang punya akun (Admin & Editor tidak ditagih iuran) ---
            $org->members()->create(['user_id' => $nadia->id, 'name' => $nadia->name, 'email' => $nadia->email, 'role' => 'admin', 'is_billable' => false]);
            $org->members()->create(['user_id' => $rafi->id, 'name' => $rafi->name, 'email' => $rafi->email, 'role' => 'editor', 'is_billable' => false]);
            $mDimas = $org->members()->create(['user_id' => $dimas->id, 'name' => $dimas->name, 'email' => $dimas->email, 'role' => 'anggota']);

            // --- Anggota yang belum punya akun (hanya kontak yang ditagih) ---
            $mSinta = $org->members()->create(['name' => 'Sinta Maharani', 'email' => 'sinta@kaskita.demo', 'phone' => '081200000001']);
            $mBudi = $org->members()->create(['name' => 'Budi Santoso', 'email' => 'budi@kaskita.demo', 'phone' => '081200000002']);
            $mCitra = $org->members()->create(['name' => 'Citra Lestari', 'email' => 'citra@kaskita.demo', 'phone' => '081200000003']);

            // --- Kategori ---
            $iuran = Category::create(['organization_id' => $org->id, 'name' => 'Iuran', 'is_system' => true]);
            $donasi = Category::create(['organization_id' => $org->id, 'name' => 'Donasi']);
            $konsumsi = Category::create(['organization_id' => $org->id, 'name' => 'Konsumsi']);
            $perlengkapan = Category::create(['organization_id' => $org->id, 'name' => 'Perlengkapan']);
            $transport = Category::create(['organization_id' => $org->id, 'name' => 'Transportasi']);

            // --- Tagihan Oktober 2026: lunas, belum, menunggu, lunas ---
            $invSinta = Invoice::create([
                'member_id' => $mSinta->id, 'period' => '2026-10', 'amount' => 25000,
                'status' => 'lunas', 'method' => 'transfer', 'paid_at' => '2026-10-05 10:00:00',
            ]);
            Invoice::create([
                'member_id' => $mDimas->id, 'period' => '2026-10', 'amount' => 25000,
                'status' => 'belum',
            ]);
            Invoice::create([
                'member_id' => $mBudi->id, 'period' => '2026-10', 'amount' => 25000,
                'status' => 'menunggu', 'method' => 'transfer',
                'proof_path' => 'proofs/demo-bukti-budi.svg', // berkas fiktif, belum ada di disk
            ]);
            $invCitra = Invoice::create([
                'member_id' => $mCitra->id, 'period' => '2026-10', 'amount' => 25000,
                'status' => 'lunas', 'method' => 'tunai', 'paid_at' => '2026-10-08 14:30:00',
            ]);

            // --- Transaksi otomatis dari tagihan lunas (terkunci lewat invoice_id) ---
            $this->transaksi($org, $nadia, $iuran, 'masuk', 25000, '2026-10-05', 'Iuran Sinta Maharani Oktober', $invSinta);
            $this->transaksi($org, $nadia, $iuran, 'masuk', 25000, '2026-10-08', 'Iuran Citra Lestari Oktober', $invCitra);

            // --- Transaksi manual ---
            $this->transaksi($org, $nadia, $donasi, 'masuk', 200000, '2026-10-03', 'Donasi alumni untuk kegiatan');
            $this->transaksi($org, $rafi, $konsumsi, 'keluar', 150000, '2026-10-10', 'Konsumsi rapat kerja');
            $this->transaksi($org, $rafi, $perlengkapan, 'keluar', 85000, '2026-10-15', 'Spanduk dan alat tulis');
            $this->transaksi($org, $nadia, $transport, 'keluar', 60000, '2026-10-20', 'Transport kunjungan kampus');

            // --- Kode undangan demo ---
            Invitation::create([
                'organization_id' => $org->id,
                'code' => 'BEM26KAS',
                'created_by' => $nadia->id,
            ]);
        });
    }

    private function transaksi(Organization $org, User $oleh, Category $kategori, string $tipe, int $jumlah, string $tanggal, string $ket, ?Invoice $invoice = null): void
    {
        Transaction::create([
            'organization_id' => $org->id,
            'category_id' => $kategori->id,
            'invoice_id' => $invoice?->id,
            'created_by' => $oleh->id,
            'type' => $tipe,
            'amount' => $jumlah,
            'tanggal' => $tanggal,
            'description' => $ket,
        ]);
    }
}