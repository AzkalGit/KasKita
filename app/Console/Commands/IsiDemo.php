<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data demo tambahan untuk presentasi. Jalankan SETELAH `migrate:fresh --seed`.
 * PERINGATAN: kata sandi lemah, HANYA untuk komputer lokal.
 */
class IsiDemo extends Command
{
    protected $signature = 'kaskita:isi-demo';

    protected $description = 'Tambah 24 akun demo, anggota, tagihan, dan transaksi ke 4 organisasi demo (hanya lokal)';

    private const DOMAIN = 'demo.kaskita.id';

    private const PASSWORD = 'demo1234';

    private const NAMA = [
        'Aditya Pratama', 'Bagas Setiawan', 'Citra Ayu Lestari', 'Dewi Anggraini',
        'Eko Prasetyo', 'Fitri Handayani', 'Gilang Ramadhan', 'Hana Salsabila',
        'Indra Kurniawan', 'Jihan Maharani', 'Kevin Wijaya', 'Lestari Wulandari',
        'Maya Safitri', 'Nanda Putra', 'Okta Permana', 'Putri Rahmawati',
        'Rizky Maulana', 'Salsa Nabila', 'Taufik Hidayat', 'Umi Kulsum',
        'Vina Oktaviani', 'Wahyu Nugroho', 'Yoga Saputra', 'Zahra Amelia',
    ];

    private const ORGANISASI = [
        'HMMI', 'HMMI Divisi Kewirausahaan', 'BEM', 'Komunitas Freelancer Digital Nusantara',
    ];

    // Pola tetap (bukan acak) supaya hasilnya selalu sama
    private const POLA_OKT = ['lunas', 'lunas', 'lunas', 'belum', 'lunas', 'menunggu', 'lunas', 'belum'];

    private const POLA_NOV = ['lunas', 'belum', 'belum', 'menunggu', 'belum', 'lunas', 'belum', 'belum'];

    // [kategori, jenis, jumlah, tanggal, keterangan]
    private const TRANSAKSI_TAMBAHAN = [
        'HMMI' => [
            ['Donasi', 'masuk', 250000, '2026-11-08', 'Donasi alumni angkatan 2019'],
            ['Konsumsi', 'keluar', 120000, '2026-11-09', 'Konsumsi rapat evaluasi'],
            ['Perlengkapan', 'keluar', 75000, '2026-10-25', 'Map dan penjepit kertas'],
            ['Transportasi', 'keluar', 45000, '2026-10-28', 'Transport pengantaran surat'],
        ],
        'HMMI Divisi Kewirausahaan' => [
            ['Penjualan', 'masuk', 380000, '2026-11-10', 'Hasil penjualan pre-order produk'],
            ['Bahan Produksi', 'keluar', 180000, '2026-10-27', 'Bahan baku produksi kedua'],
            ['Promosi', 'keluar', 35000, '2026-11-07', 'Iklan media sosial'],
            ['Kemasan', 'keluar', 55000, '2026-10-30', 'Stiker label produk'],
        ],
        'BEM' => [
            ['Donasi', 'masuk', 350000, '2026-11-09', 'Sumbangan dosen untuk kegiatan'],
            ['Kegiatan', 'keluar', 400000, '2026-11-11', 'Pelatihan kepemimpinan'],
            ['Konsumsi', 'keluar', 95000, '2026-10-29', 'Konsumsi rapat koordinasi'],
            ['Perlengkapan', 'keluar', 65000, '2026-10-27', 'Alat tulis sekretariat'],
        ],
        'Komunitas Freelancer Digital Nusantara' => [
            ['Sponsor', 'masuk', 600000, '2026-11-10', 'Sponsor kelas desain'],
            ['Sewa Tempat', 'keluar', 350000, '2026-11-06', 'Sewa ruang co-working November'],
            ['Acara Kopdar', 'keluar', 150000, '2026-10-31', 'Konsumsi kopdar akhir bulan'],
            ['Langganan Alat', 'keluar', 85000, '2026-11-11', 'Langganan alat kolaborasi'],
        ],
    ];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini hanya boleh dijalankan di lingkungan lokal (kata sandi demo lemah).');

            return self::FAILURE;
        }

        if (User::where('email', 'like', '%@'.self::DOMAIN)->exists()) {
            $this->error('Data demo tambahan sudah ada. Jalankan `php artisan migrate:fresh --seed` dulu bila ingin mengulang.');

            return self::FAILURE;
        }

        $orgs = Organization::whereIn('name', self::ORGANISASI)->get()->keyBy('name');
        if ($orgs->count() !== count(self::ORGANISASI)) {
            $this->error('Empat organisasi demo belum ada. Jalankan `php artisan migrate:fresh --seed` dulu.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($orgs) {
            $urut = [];   // jumlah anggota baru per organisasi (anggota pertama = Editor)
            $nomor = 0;   // penentu pola tagihan

            foreach (self::NAMA as $i => $nama) {
                $user = User::forceCreate([
                    'name' => $nama,
                    'email' => Str::slug($nama, '.').'@'.self::DOMAIN,
                    'password' => self::PASSWORD, // di-hash oleh cast 'hashed'
                    'email_verified_at' => now(),
                ]);

                $indeks = [$i % 4];
                if ($i % 3 === 0) {
                    $indeks[] = ($i + 1) % 4;
                }

                foreach ($indeks as $k) {
                    $org = $orgs[self::ORGANISASI[$k]];
                    $urut[$org->id] = ($urut[$org->id] ?? -1) + 1;
                    $peran = $urut[$org->id] === 0 ? 'editor' : 'anggota';

                    $anggota = $org->members()->create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $peran,
                        'is_billable' => $peran === 'anggota',
                    ]);

                    if ($peran === 'anggota') {
                        $this->buatTagihan($org, $anggota, '2026-10', self::POLA_OKT[$nomor % 8], $nomor);
                        $this->buatTagihan($org, $anggota, '2026-11', self::POLA_NOV[$nomor % 8], $nomor);
                        $nomor++;
                    }
                }
            }

            foreach (self::TRANSAKSI_TAMBAHAN as $namaOrg => $daftar) {
                $this->buatTransaksiTambahan($orgs[$namaOrg], $daftar);
            }
        });

        $this->info('Selesai: '.count(self::NAMA).' akun demo ditambahkan. Kata sandi semua akun tambahan: '.self::PASSWORD);

        return self::SUCCESS;
    }

    private function buatTagihan(Organization $org, OrganizationMember $anggota, string $periode, string $status, int $nomor): void
    {
        $data = [
            'member_id' => $anggota->id,
            'period' => $periode,
            'amount' => $org->default_dues,
            'status' => $status,
        ];

        if ($status === 'menunggu') {
            $data['method'] = 'transfer'; // tanpa berkas bukti
        }

        if ($status !== 'lunas') {
            Invoice::create($data);

            return;
        }

        $tanggal = $periode === '2026-10'
            ? sprintf('2026-10-%02d', 3 + ($nomor * 2) % 25)
            : sprintf('2026-11-%02d', 2 + $nomor % 10);

        $invoice = Invoice::create($data + [
            'method' => $nomor % 2 === 0 ? 'tunai' : 'transfer',
            'paid_at' => $tanggal.' 10:00:00',
        ]);

        // Sama seperti Invoice::settle(): transaksi otomatis, kategori Iuran, tanpa nama anggota (D2)
        Transaction::create([
            'organization_id' => $org->id,
            'category_id' => $this->kategori($org, 'Iuran')->id,
            'invoice_id' => $invoice->id,
            'created_by' => $this->pelaku($org, 'admin'),
            'type' => 'masuk',
            'amount' => $org->default_dues,
            'tanggal' => $tanggal,
            'description' => 'Iuran '.Invoice::labelFor($periode),
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int, 3: string, 4: string}>  $daftar
     */
    private function buatTransaksiTambahan(Organization $org, array $daftar): void
    {
        foreach ($daftar as [$kategori, $jenis, $jumlah, $tanggal, $ket]) {
            Transaction::create([
                'organization_id' => $org->id,
                'category_id' => $this->kategori($org, $kategori)->id,
                'created_by' => $jenis === 'masuk'
                    ? $this->pelaku($org, 'admin')
                    : ($this->pelaku($org, 'editor') ?? $this->pelaku($org, 'admin')),
                'type' => $jenis,
                'amount' => $jumlah,
                'tanggal' => $tanggal,
                'description' => $ket,
            ]);
        }
    }

    private function kategori(Organization $org, string $nama): Category
    {
        return $org->categories()->where('name', $nama)->firstOrFail();
    }

    private function pelaku(Organization $org, string $peran): ?int
    {
        return $org->members()->where('role', $peran)->whereNotNull('user_id')->orderBy('id')->value('user_id');
    }
}
