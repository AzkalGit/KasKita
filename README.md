# KasKita

Aplikasi kas organisasi berbasis web: mencatat **iuran, donasi, dan pengeluaran**, menagih iuran bulanan, memverifikasi bukti bayar, dan menyusun laporan keuangan. Satu akun bisa ikut banyak organisasi, dan peran (Admin, Editor, Anggota) berlaku **per organisasi**.

> Panduan ini ditulis untuk pengurus dan anggota organisasi. Cara memasang dan menjalankan aplikasi ada di bagian [Pemasangan lokal](#pemasangan-lokal-untuk-developer-dan-penguji).

## Daftar isi

1. [Untuk siapa](#untuk-siapa)
2. [Tiga peran dan hak masing-masing](#tiga-peran-dan-hak-masing-masing)
3. [Alur dari awal sampai akhir](#alur-dari-awal-sampai-akhir)
4. [Tanya-jawab](#tanya-jawab)
5. [Belum tersedia](#belum-tersedia)
6. [Pemasangan lokal](#pemasangan-lokal-untuk-developer-dan-penguji)
7. [Pengujian](#pengujian)
8. [Catatan keamanan](#catatan-keamanan)

## Untuk siapa

- **Pengurus** (bendahara, ketua) yang ingin kas tercatat rapi dan transparan.
- **Anggota** yang ingin tahu tagihan iurannya dan ke mana uang kas dipakai.
- **Pemilik platform (Owner)**, peran terpisah yang hanya mengelola daftar organisasi, tanpa melihat data keuangan.

## Tiga peran dan hak masing-masing

| Kemampuan | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: |
| Melihat dashboard, transaksi, dan tagihan (Anggota hanya tagihan miliknya) | ya | ya | ya |
| Melihat laporan dan mengunduh CSV | ya | ya | – |
| Melihat tunggakan di laporan | ya | – | – |
| Mencatat transaksi, mengelola tagihan iuran | ya | ya | – |
| Menghapus transaksi | ya | – | – |
| Mengelola anggota (termasuk menjadikan Admin), kode undangan, pengaturan organisasi, kategori | ya | – | – |
| Tutup buku bulan lampau | ya | – | – |

Catatan:
- Orang yang bergabung lewat kode undangan selalu masuk sebagai **Anggota**; Admin yang mengubah perannya.
- Admin dan Editor tidak ditagih secara bawaan. Jika ingin ikut membayar iuran, Admin menandai mereka sebagai **ditagih**.

## Alur dari awal sampai akhir

> _Tempat tangkapan layar: tambahkan sendiri bila perlu._

### 1. Daftar dan masuk
Buat akun di halaman **Daftar**. Di server sungguhan, email wajib **diverifikasi** lewat tautan yang dikirim. Setelah masuk, kamu melihat daftar organisasimu di `/organisasi`.

### 2. Buat atau gabung organisasi
- **Buat baru:** kamu otomatis menjadi **Admin**-nya.
- **Gabung:** masukkan **kode undangan** dari Admin organisasi. Kamu masuk sebagai Anggota.

### 3. Atur organisasi (Admin)
- Atur **kategori** pemasukan/pengeluaran (kategori "Iuran" sudah ada dan terkunci).
- Isi **info pembayaran** (rekening, nama penerima). Ini tampil di halaman Iuran supaya anggota tahu cara membayar.
- Buat dan kelola **kode undangan**.

### 4. Tambah anggota
Anggota masuk lewat kode undangan, atau Admin menambahkan **kontak** (nama dan email) yang belum punya akun. Kontak otomatis tertaut ke akunnya begitu orang itu mendaftar dan memverifikasi email yang sama. Admin bisa mengubah peran (Anggota ↔ Editor, atau jadikan Admin), menonaktifkan/mengaktifkan anggota, dan menandai siapa yang ditagih.

### 5. Buat tagihan iuran bulanan (Admin/Editor)
Di halaman **Iuran**, buat tagihan untuk satu bulan. Tagihan dibuat untuk anggota yang ditandai ditagih, sebesar iuran bawaan organisasi.

### 6. Anggota membayar
Anggota membayar sesuai info pembayaran, lalu di halaman Iuran memilih **Ajukan pembayaran** dan mengunggah **bukti bayar** (disimpan privat, hanya bisa diunduh oleh yang berhak). Status tagihan menjadi `menunggu`. Pengajuan bisa **ditarik** selama belum diputuskan.

Pembayaran tunai bisa langsung ditandai **Lunas tunai** oleh Admin/Editor.

### 7. Verifikasi (Admin/Editor)
Admin atau Editor memeriksa bukti lalu **menyetujui** (tagihan `lunas`) atau **menolak** dengan alasan (anggota bisa mengajukan lagi). Pelunasan juga bisa **dibatalkan**.

> **Aturan tagihan sendiri:** tak seorang pun boleh melunasi, menyetujui, menolak, atau membatalkan pelunasan tagihannya sendiri. Pengelola lain yang memverifikasi.

### 8. Transaksi tercatat otomatis
Setiap tagihan yang lunas otomatis membuat **transaksi pemasukan** berkategori Iuran dengan keterangan "Iuran {Bulan} {Tahun}" (tanpa nama anggota). Transaksi otomatis ini **terkunci**. Pemasukan lain (donasi dll.) dan pengeluaran dicatat manual oleh Admin/Editor di halaman **Transaksi**; hanya Admin yang bisa menghapus. Saldo dashboard = saldo awal + pemasukan − pengeluaran.

### 9. Laporan dan unduh CSV
Halaman **Laporan** menampilkan saldo awal dan akhir, rincian per kategori, dan daftar transaksi, dengan filter bulan dan minggu. Bagian tunggakan hanya terlihat oleh Admin. Laporan bisa diunduh sebagai **CSV**.

### 10. Tutup buku (Admin)
Setelah satu bulan lampau selesai, Admin bisa **menutupnya**. Bulan yang tertutup menolak tambah, ubah, pindah, dan hapus transaksi serta beberapa aksi iuran. Anggota tetap bisa mengajukan pembayaran. Bulan yang tertutup bisa **dibuka lagi** oleh Admin.

### 11. Keluar atau serah-terima
Semua anggota aktif boleh **keluar** dari organisasi (tombol Keluar di daftar organisasi); riwayat keuangannya tetap tersimpan. **Admin** hanya bisa keluar bila ada Admin lain yang aktif, atau tidak ada anggota berakun lain yang aktif. Jadi sebelum keluar, angkat dulu penggantinya lewat **Jadikan Admin**.

## Tanya-jawab

**Kenapa email harus diverifikasi?**
Supaya akun benar-benar milik pemilik email, dan kontak yang ditambahkan Admin hanya tertaut ke orang yang tepat. Di lingkungan lokal pengembangan (`APP_ENV=local`) verifikasi otomatis dilewati agar mudah diuji.

**Kenapa tagihan sendiri tidak boleh saya lunasi?**
Agar tidak ada yang menyetujui uangnya sendiri. Siapa pun, termasuk Admin, perlu diverifikasi orang lain.

**Apa itu tutup buku?**
Mengunci satu bulan lampau supaya angka laporannya tidak berubah diam-diam. Admin bisa membukanya lagi bila memang ada koreksi.

**Apakah nama saya terlihat di transaksi iuran?**
Tidak. Keterangan transaksi iuran hanya berisi bulan dan tahun. Tunggakan per orang hanya terlihat oleh Admin.

**Kalau Admin satu-satunya ingin keluar?**
Harus mengangkat Admin pengganti dulu, kecuali tak ada anggota berakun lain yang aktif.

**Siapa yang bisa melihat data keuangan?**
Anggota organisasi sesuai perannya. Owner platform tidak bisa melihat data keuangan, hanya metadata organisasi (nama, status).

**Organisasi saya dinonaktifkan Owner, bagaimana?**
Anggotanya mendapat halaman 403 sampai Owner mengaktifkannya lagi.

## Belum tersedia

- Lampiran nota untuk transaksi manual (bukti bayar iuran sudah ada).
- Hapus organisasi oleh Admin.
- Owner menunjuk Admin pada organisasi yang kehilangan Admin.
- Ekspor selain CSV (tidak ada PDF).
- Filter kategori dan rentang tanggal serta ekspor CSV di halaman Transaksi.

## Pemasangan lokal (untuk developer dan penguji)

**Kebutuhan:** PHP ≥ 8.4.1 (ekstensi `pdo_mysql`, `mbstring`, `fileinfo`), Composer, Node 22, MariaDB/MySQL, Git.

> **Cabang:** kode aplikasi ada di cabang **`fitur/laravel-fondasi`**. Cabang `main` berisi prototipe statis lama.

### Linux / macOS

```bash
git clone https://github.com/AzkalGit/KasKita.git
cd KasKita
git checkout fitur/laravel-fondasi
composer install
cp .env.example .env
php artisan key:generate
```

### Windows

Cara termudah: pasang **Laragon** (membawa PHP, MySQL, Composer; pastikan PHP 8.4) dan **Node 22**, lalu di terminal Laragon:

```bat
git clone https://github.com/AzkalGit/KasKita.git
cd KasKita
git checkout fitur/laravel-fondasi
composer install
copy .env.example .env
php artisan key:generate
```

### Lanjutan (semua sistem)

1. Buat database kosong (mis. `kaskita`) dan, bila perlu, pengguna khusus.
2. Edit `.env`: isi `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, dan pastikan **`APP_ENV=local`**.
3. Jalankan:

```bash
php artisan migrate:fresh --seed       # MENGHAPUS isi database; 1 Owner + 3 akun utama + 4 organisasi
php artisan kaskita:isi-demo           # opsional: +24 akun demo, tagihan, transaksi (hanya local/testing)
npm install
npm run dev
```

4. Di terminal kedua: `php artisan serve`, lalu buka `http://127.0.0.1:8000`.

**Akun demo:** nama pengguna dan kata sandi **tidak dicantumkan di repo**. Mintalah kepada pemilik proyek.

**Tampilan tampak tanpa gaya?** Hentikan `npm run dev`, jalankan `php artisan optimize:clear`, jalankan `npm run dev` lagi, lalu muat ulang paksa browser (Ctrl+Shift+R).

**Email verifikasi:** `MAIL_MAILER=log`, jadi email tidak benar-benar terkirim; isinya ditulis ke `storage/logs/laravel.log`. Di `APP_ENV=local` verifikasi otomatis dilewati.

## Pengujian

```bash
php artisan test
```

Tes memakai SQLite di memori, tanpa menyentuh database lokalmu.

## Catatan keamanan

- Seeder dan `kaskita:isi-demo` memuat kata sandi tetap: **jangan dijalankan di server publik**.
- Jangan mengubah `APP_ENV=local` di server sungguhan; di `local` verifikasi email dilewati.
- Jangan commit `.env`, `vendor/`, `node_modules/`, atau `*.sqlite`.
- Bukti bayar disimpan di penyimpanan privat dan diunduh lewat rute yang memeriksa hak akses.