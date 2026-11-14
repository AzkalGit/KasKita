# KasKita

Aplikasi pencatatan kas organisasi (iuran, donasi, pengeluaran) berbasis web. Satu akun bisa ikut banyak organisasi, dan **peran ditentukan per organisasi**: Admin, Editor, atau Anggota. Ada juga peran **Owner** di tingkat platform yang terpisah dari organisasi dan hanya melihat metadata, tidak data keuangan.

> **Status: aplikasi Laravel yang sudah berjalan, belum dirilis.** Cocok untuk demo dan penilaian di komputer lokal. Belum semua halaman ditelusuri di browser sungguhan (lihat bagian "Status pengujian").

Proyek kuliah Sistem Informasi, semester 3.

## Fitur

- **Akun:** daftar dan masuk (Laravel Breeze), email wajib terverifikasi.
- **Organisasi dan undangan:** buat organisasi (pembuat menjadi Admin), gabung dengan kode undangan (menjadi Anggota), kelola kode undangan.
- **Anggota:** ubah peran Anggota dan Editor, nonaktifkan atau aktifkan, tandai anggota yang ditagih iuran.
- **Kategori dan transaksi:** catat pemasukan dan pengeluaran, filter per bulan, jenis, dan kata kunci. Saldo dashboard dihitung dari saldo awal ditambah pemasukan dikurangi pengeluaran.
- **Iuran:** buat tagihan bulanan, lunas tunai, ajukan pembayaran dengan bukti (disimpan privat), setujui atau tolak. Saat lunas, pemasukan otomatis tercatat dan terkunci.
- **Laporan:** per bulan atau minggu, saldo awal dan akhir, rincian per kategori, daftar tunggakan (khusus Admin), unduh CSV.
- **Tutup buku:** Admin menutup bulan lampau, sehingga transaksi bulan itu tidak bisa ditambah, diubah, atau dihapus. Bulan bisa dibuka lagi.
- **Panel Owner:** aktifkan atau nonaktifkan organisasi. Organisasi nonaktif tidak bisa diakses anggotanya.
- **Log aktivitas** untuk aksi penting.

## Hak akses per peran

| Kemampuan | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: |
| Lihat dashboard, transaksi, tagihan (Anggota hanya tagihan miliknya) | ya | ya | ya |
| Lihat laporan dan unduh CSV | ya | ya | – |
| Lihat tunggakan | ya | – | – |
| Kelola transaksi dan tagihan | ya | ya | – |
| Hapus transaksi, kelola anggota, undangan, pengaturan, tutup buku | ya | – | – |

Tak seorang pun, termasuk Admin, bisa melunasi, menyetujui, atau membatalkan tagihannya sendiri. Pengelola lain yang memverifikasi.

## Teknologi

- Laravel 13, PHP 8.4.1 atau lebih baru
- Blade dengan Tailwind CSS lewat Vite
- MariaDB atau MySQL untuk penggunaan lokal, SQLite di memori untuk tes
- Node.js 22

## Menjalankan di komputer lokal

Prasyarat: PHP 8.4+, Composer, Node.js, dan MariaDB atau MySQL dengan database kosong.

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi pengaturan DB_* di .env sesuai database lokalmu
php artisan migrate:fresh --seed     # MENGHAPUS isi database lokal
php artisan kaskita:isi-demo         # opsional: akun, tagihan, dan transaksi demo
npm install
```

Lalu jalankan di dua terminal:

```bash
npm run dev
php artisan serve
```

Buka `http://127.0.0.1:8000`.

`migrate:fresh --seed` membuat satu akun Owner, tiga akun utama dengan peran berbeda di tiap organisasi, dan empat organisasi demo. `kaskita:isi-demo` menambah akun, tagihan, dan transaksi (hanya jalan di lingkungan `local` atau `testing`). Daftar akun dan kata sandi demo tidak dicantumkan di sini. Lihat seeder di `database/seeders/` atau tanyakan kepada pemilik repo.

> **Peringatan:** seeder memuat kata sandi tetap dan lemah. Jangan menjalankannya di server publik.

Bila tampilan tampak tanpa gaya: hentikan `npm run dev`, jalankan `php artisan optimize:clear`, jalankan `npm run dev` lagi, lalu muat ulang browser dengan Ctrl+Shift+R.

## Menjalankan tes

```bash
php artisan test
```

Pada pembaruan terakhir, 236 tes otomatis lulus. Jalankan ulang untuk angka terkini.

## Status pengujian

- Tes otomatis mencakup otorisasi, isolasi antar organisasi, iuran, laporan, dan tutup buku.
- Seeder dan perintah `kaskita:isi-demo` sudah berjalan di MariaDB lokal.
- Sebagian halaman belum ditelusuri langsung di browser: tampilan layar HP, alur beberapa peran, dan ekspor CSV di Excel atau LibreOffice. CSV berkoma bisa terbuka dalam satu kolom di Excel berlokal Indonesia.

## Keterbatasan yang diketahui

- Zona waktu dan bahasa aplikasi belum diatur ke Indonesia. Tanggal pelunasan dan bulan bawaan laporan bisa meleset sehari.
- Pencarian transaksi memperlakukan `%` dan `_` sebagai wildcard.
- Laporan tidak dipaginasi.
- Lampiran nota untuk transaksi manual belum ada. Yang tersedia baru bukti bayar iuran.
- Laporan hanya tersedia dalam bentuk CSV, tanpa PDF.
- Belum ada serah-terima Admin, promosi ke Admin, atau hapus organisasi dari antarmuka.

## Struktur repositori

| Lokasi | Isi |
| --- | --- |
| `app/` | Controller, Policy, Model, middleware, `ActivityLogger` |
| `database/` | Migrasi dan seeder |
| `resources/views/` | Halaman Blade per modul |
| `routes/web.php` | Rute aplikasi |
| `tests/Feature/` | Tes per modul |
| `docs/RIWAYAT.md` | Riwayat langkah pengembangan dan aturan bisnis rinci |
| `CLAUDE.md` | Catatan kerja dan serah-terima antar sesi pengembangan |
| `prototipe-statis/` | Prototipe HTML awal (arsip) |

## Cabang

- `main`: prototipe statis yang tampil di GitHub Pages.
- `fitur/laravel-fondasi`: versi Laravel (cabang kerja).

Repositori: https://github.com/AzkalGit/KasKita