# KasKita

Aplikasi pencatatan kas organisasi (iuran, donasi, pengeluaran) dengan peran **Owner/Dev, Admin, Editor, dan Anggota**. Satu akun bisa ikut banyak organisasi, dan peran ditentukan per organisasi.

> **Status: prototipe statis untuk desain dan alur.** HTML + CSS + satu file JavaScript, data disimpan di `localStorage` browser. Tidak ada server atau database. Rencana berikutnya adalah mengimplementasikannya di **Laravel** (lihat [CLAUDE.md](CLAUDE.md), bagian "Panduan migrasi ke Laravel").
>
> Dokumen ini disusun dari pembacaan kode pada commit `4a99c24` (9 Oktober 2026). Catatan teknis dari sesi sebelumnya ada di [KASKITA-STATUS.txt](KASKITA-STATUS.txt).

## Menjalankan

Tidak perlu build atau instalasi.

1. Buka `index.html` di browser (otomatis diarahkan ke halaman masuk), **atau** layani foldernya dengan server statis apa pun, misalnya Live Server di VS Code atau folder `www` Laragon.
2. Perlu internet hanya untuk memuat font Plus Jakarta Sans dari Google Fonts. Tanpa internet, tampilan tetap berjalan dengan font cadangan.

Data tersimpan **per alamat (origin)**. Membuka lewat `file://` dan lewat `http://localhost` dianggap dua tempat penyimpanan berbeda, jadi data tidak akan muncul di keduanya sekaligus. Pilih salah satu cara dan konsisten.

## Akun demo

Kata sandi semua akun demo: `KitaDemo2026!`

| Profil | Email | Yang bisa dilakukan |
| --- | --- | --- |
| Owner/Dev | `owner@kaskita.demo` | Hanya panel platform: status organisasi (aktif/nonaktif), reset data demo |
| Admin | `admin@kaskita.demo` | Semua fitur organisasi |
| Editor | `editor@kaskita.demo` | Catat dan ubah transaksi, baca laporan |
| Anggota | `anggota@kaskita.demo` | Lihat dan bayar tagihan iuran sendiri |

Organisasi demo: **BEM Fakultas Teknologi**, kode undangan `BEM26KAS`. Seluruh isinya fiktif.

## Alur utama

1. **Daftar** akun (tanpa memilih organisasi).
2. Di halaman **Organisasi**: *buat organisasi baru* (pembuat otomatis menjadi **Admin**) atau *gabung dengan kode undangan* (menjadi **Anggota**).
3. **Admin** membagikan kode undangan dari halaman Anggota, menambahkan anggota, mengubah Anggota menjadi Editor (atau sebaliknya), membuat tagihan iuran, dan memverifikasi bukti pembayaran.
4. **Anggota** membuka tagihan miliknya, mengunggah bukti transfer (gambar), lalu menunggu verifikasi.
5. Saat Admin menyetujui, tagihan menjadi *Lunas* dan **pemasukan otomatis tercatat** di transaksi.

## Hak akses per peran

| Halaman | Owner/Dev | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: | :-: |
| Panel Owner/Dev (`owner.html`) | ya | – | – | – |
| Organisasi (pilih, buat, gabung) | – | ya | ya | ya |
| Dashboard | – | ya | ya | – |
| Transaksi: lihat | – | ya | ya | – |
| Transaksi: catat dan ubah | – | ya | ya | – |
| Transaksi: hapus | – | ya | – | – |
| Iuran: kelola tagihan dan verifikasi | – | ya | – | – |
| Iuran: lihat dan bayar milik sendiri | – | – | – | ya |
| Anggota dan kode undangan | – | ya | – | – |
| Laporan (termasuk tunggakan) | – | ya | ya (tanpa tunggakan) | – |
| Pengaturan | – | ya | – | – |

Tabel ini menggambarkan **perilaku kode saat ini**. Pembatasan ini hanya tampilan di sisi browser, bukan keamanan sungguhan.

## Halaman

| File | Fungsi |
| --- | --- |
| `index.html` | Pengalihan ke `login.html` |
| `login.html`, `register.html` | Masuk dan daftar |
| `organisasi.html` | Daftar organisasi, buat, gabung dengan kode |
| `dashboard.html` | Saldo, ringkasan per periode, grafik 6 bulan, transaksi terakhir |
| `transaksi.html` | Cari dan filter, lampiran nota, ekspor CSV |
| `iuran.html` | Tagihan: tampilan kelola (Admin) dan tampilan pribadi (Anggota) |
| `anggota.html` | Daftar anggota, peran, kode undangan |
| `laporan.html` | Arus kas, penggunaan dana per kategori, rekap bulanan, ekspor CSV, cetak |
| `pengaturan.html` | Saldo awal, nominal iuran, info pembayaran, kategori |
| `owner.html` | Panel Owner/Dev: metadata organisasi saja |
| `css/kaskita.css`, `js/kaskita.js` | Seluruh gaya dan seluruh logika |

## Batasan yang perlu diketahui

- **Kata sandi disimpan polos di browser** dan pemeriksaan peran hanya di JavaScript. Siapa pun yang membuka DevTools bisa mengubah peran atau data. Jangan pakai sebagai aplikasi sungguhan.
- Data tidak tersinkron antar perangkat atau browser. Menghapus data situs menghapus semua catatan.
- Lampiran (nota, bukti bayar) hanya gambar. Gambar diperkecil ke maksimal 900 px dan disimpan di `localStorage`, yang kuotanya sekitar 5 MB.
- Belum dikerjakan: laporan per minggu, ekspor PDF (saat ini hanya cetak lewat browser), tutup buku, riwayat aktivitas, kode undangan berbatas waktu, promosi ke Admin.
- **Bug yang diketahui:** tombol "Isi data contoh" di organisasi yang masih kosong menimbulkan error (`seed is not defined`). Rincian dan temuan lain ada di [CLAUDE.md](CLAUDE.md), bagian "Masalah yang diketahui".
- Belum diuji klik langsung di browser sungguhan. Pemeriksaan sejauh ini memakai simulasi DOM.

## Berkas dokumentasi

- [`CLAUDE.md`](CLAUDE.md): panduan teknis untuk melanjutkan pengembangan (arsitektur, model data, aturan, rencana Laravel).
- [`KASKITA-STATUS.txt`](KASKITA-STATUS.txt): catatan status dari sesi pengembangan sebelumnya.

Repositori: https://github.com/AzkalGit/KasKita
