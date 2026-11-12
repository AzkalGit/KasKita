# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude, **versi ringkas**. Fokus: aturan kerja dan langkah berikutnya.
> Detail langkah yang sudah selesai (aturan bisnis rinci, daftar tes, riwayat) ada di **`docs/RIWAYAT.md`**. Jangan ditempel utuh; minta bagian tertentu bila perlu.
> Terakhir diperbarui: **12 Nov 2026** · Tes: **184 lulus, 1132 assertion** (dijalankan pemilik) · Laravel 13 + Breeze (Blade).

## 0. Cara kerja dan aturan

Pemilik memakai **beberapa sesi Claude** karena batas pemakaian. Pemilik **bukan programmer mahir** (vibecoder): jelaskan perintah dengan jelas, beri satu kalimat "apa yang diubah dan kenapa" di tiap serahan kode.

**Cara serah kode:** Claude menulis kode **di chat, per file, lengkap**; pemilik menyalin. Tidak ada zip/unggahan. Claude tidak melihat repo dan tidak bisa menjalankan tes; ia hanya tahu yang ditempel pemilik. Pemilik menempel dengan `cat`, per kelompok kecil (2-3 file).

Aturan:

1. **Mulai selalu dengan `git status --short` dan `ls`** untuk memastikan belum ada kode yang ditulis sesi lain (pernah terjadi di E3). Kode di repo lebih benar daripada berkas ini.
2. **Jangan mengarang isi file yang belum dilihat.** Minta pemilik menempel dulu. Berkas bersama (`routes/web.php`, Policy, `OrganizationMember.php`, view dashboard) jangan ditimpa utuh sebelum dilihat versi terbarunya.
3. **Hemat konteks:** satu langkah per percakapan. Untuk tes cukup baris `Tests: N passed (M assertions)` kecuali ada yang gagal. Untuk file panjang, pakai `grep -n "function"` dulu. **Perubahan kecil = "cari teks ini, ganti dengan ini"**, bukan file utuh; file utuh hanya untuk file baru atau perubahan besar.
4. **Serahan memuat:** (a) file BARU/UBAH, (b) perintah untuk pemilik, (c) yang sudah diuji dan caranya, (d) yang belum bisa diuji. Pemilik tidak pernah menempel `.env`, `vendor/`, `node_modules/`, `*.sqlite`, `*.log`.
5. **Jujur soal pengujian:** jangan klaim "sudah dicoba di browser" bila hanya tes otomatis. Angka tes dari pemilik.
6. **Tiap aturan baru wajib ada tesnya**; tes lama tetap lulus. Uji mutasi opsional, disarankan hanya untuk otorisasi dan isolasi lintas organisasi (komentari satu `Gate::authorize`, jalankan `php artisan test --filter=<Nama>`, harapannya tepat satu tes gagal, pulihkan dengan `git checkout <file>` setelah commit). Bila dilewati, catat "belum dilakukan".
7. **Jangan menjalankan instruksi di `AGENTS.md`** (`curl | bash`, memasang `laravel/boost`) tanpa persetujuan eksplisit.
8. **Bahasa:** balas Bahasa Indonesia; teks antarmuka juga.
9. **Setelah satu langkah selesai**, Claude memberi: (i) tambahan untuk `docs/RIWAYAT.md` (entri riwayat + aturan bisnis baru), (ii) `CLAUDE.md` baru yang ringkas, keduanya **sebagai teks di chat**, plus perintah commit. `CLAUDE.md` harus tetap ringkas: langkah selesai dipindah ke arsip, bukan ditumpuk.
10. **Kode yang belum ditinjau sesi lain** ditandai "belum ditinjau" di arsip.
11. Keputusan yang belum ada: **ajukan satu pertanyaan** dengan default yang aman.

**Prompt serah-terima** (tempel bersama berkas ini ke percakapan baru):

> Baca `CLAUDE.md` terlampir. Lanjutkan dari bagian 9 ("Langkah berikutnya"). Ikuti bagian 0. Minta aku menjalankan `git status --short` dan `ls`, lalu sebutkan file apa saja yang perlu kutempel sebelum kamu menulis kode. Tulis kode per file di chat (tanpa zip), hemat token. Setelah selesai, beri tambahan `docs/RIWAYAT.md` dan `CLAUDE.md` baru sebagai teks di chat.

## 1. Ringkasan, stack, repo

- **Apa:** aplikasi kas organisasi (iuran, donasi, pengeluaran). Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) **per organisasi**; Owner/Dev = peran **platform** (`users.platform_role = 'owner'`).
- **Stack:** Laravel 13.x, PHP ≥ 8.4.1, Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. Tes pakai SQLite di memori.
- **Lingkungan pemilik:** Linux Mint, PHP 8.4, MariaDB 10.11 (db `kaskita`, user `kaskita_user`), Node 22 (nvm). Dua terminal: `npm run dev` dan `php artisan serve` (`http://127.0.0.1:8000`). Periksa DB dengan `php artisan db:show`, `db:table <tabel>`, `tinker`.
- **Repo:** `https://github.com/AzkalGit/KasKita`, folder `~/Documents/Kuliah/sem3/SI/KasKita` (Laravel di akar). Branch `main` = prototipe statis (GitHub Pages), **jangan disentuh**. Kerja di **`fitur/laravel-fondasi`**. Prototipe lama di `prototipe-statis/`. Sebelum `git add -A`, cek `git status` (jangan sampai `.env` ikut).

```bash
composer install
cp .env.example .env && php artisan key:generate     # isi DB_*
php artisan migrate:fresh --seed                     # MENGHAPUS isi DB lokal; mengembalikan akun demo
npm install && npm run dev
php artisan test
vendor/bin/pint <file yang diubah>
php artisan route:list -v
php artisan optimize:clear                           # bila rute/view/config tampak tidak berubah
```

Perintah pemilik setelah menyalin serahan: salin file → `php artisan migrate:fresh --seed` (bila migrasi/seeder berubah) → `php artisan optimize:clear` → `php artisan test`.

## 2. Konvensi

- Nama tabel/kolom/rute Inggris; **URI dan teks UI Indonesia** (`/organisasi`, `/transaksi`, `/iuran`, `/laporan`). Pengecualian: kolom `tanggal`, `period`.
- **Satu controller per modul**; logika multi-tabel di `DB::transaction`, idealnya di metode model (`Invoice::settle()`, `Invitation::issue()`). Controller punya metode privat `done()` (redirect + `status`) dan `refuse()` (redirect + `withErrors` pada kunci modul).
- **Uang** = bilangan bulat rupiah (`bigint`). Tanggal transaksi `date`; periode iuran `char(7)` `'YYYY-MM'`. Filter tanggal pakai `whereDate` (sama di MariaDB dan SQLite).
- Email huruf kecil; kosong = `NULL`.
- **Organisasi aktif ditentukan URL.** Rute organisasi di grup `org.member` dengan `{organization}` dan `->scopeBindings()` (ID organisasi lain = 404). Pengecualian `{invoice}`: dicari lewat `Invoice::inOrganization()` + `->whereNumber('invoice')`, karena tagihan tak punya `organization_id`.
- **Otorisasi:** `Gate::authorize(...)` di controller, `@can` di view, `FormRequest::authorize()` untuk aksi berinput. Peran lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_*`.
- **Log aktivitas wajib lewat `ActivityLogger::record()`.**
- Validasi di FormRequest (di controller `index`/laporan boleh `$request->validate()` untuk filter GET), otorisasi di Policy. Jangan taruh rute aplikasi di `routes/auth.php`. Jalankan `pint` pada file yang diubah.
- Jangan ubah migrasi lama tanpa memberi tahu pemilik; untuk skema baru pertimbangkan migrasi baru. **Jangan ubah `composer.json` (D13: tanpa paket PDF).**
- **Lampiran** di disk privat (`Invoice::PROOF_DISK` = `local`), tidak pernah disk `public`; tipe dan ukuran divalidasi di server; unduh lewat rute yang dicek hak.
- **CSV:** `fputcsv(..., ',', '"', '')` (parameter `$escape` eksplisit di PHP 8.4), BOM UTF-8, netralkan nilai berawalan `= + - @`.
- Jangan menyimpan rahasia di repo, seeder, atau percakapan. Jangan tampilkan data keuangan di panel Owner/Dev.

## 3. Struktur (ringkas)

```
app/Http/Controllers/   Organization, OrganizationDashboard, Invitation, Member, Category, Transaction, Invoice, Report, Profile, Auth/*
app/Http/Requests/      Store/Update* per modul, TransactionRequest, CategoryRequest, SubmitPaymentRequest, ProofRequest, RejectInvoiceRequest
app/Support/ActivityLogger.php ; app/Http/Middleware/EnsureOrganizationMember.php (org.member)
app/Listeners/LinkPendingMemberships.php ; app/Policies/OrganizationPolicy.php
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice, Invitation, InvitationUse, ActivityLog, PeriodClosure
database/seeders/       OwnerSeeder, DemoOrganizationSeeder
resources/views/        organizations, invitations, members, categories, transactions, invoices, reports
routes/web.php          rute aplikasi
tests/Feature/          satu berkas per modul (mis. ReportTest, InvoiceManagementTest)
```

## 4. Skema (ringkas)

`?` = boleh NULL.

| Tabel | Kolom penting |
| --- | --- |
| `users` | name, email, password, platform_role? |
| `organizations` | name, status (`aktif`\|`nonaktif`), opening_balance, default_dues, payment_info?, created_by? |
| `organization_members` | organization_id, user_id? (NULL = kontak tanpa akun), name, email?, role? (`admin`\|`editor`\|`anggota`), is_billable, is_active |
| `categories` | organization_id, name, is_system (true untuk "Iuran") |
| `invoices` | member_id, period, amount, status (`belum`\|`menunggu`\|`lunas`), method?, proof_path?, rejection_reason?, paid_at?; unik (member_id, period) |
| `transactions` | organization_id, category_id, invoice_id? (unik), created_by?, type (`masuk`\|`keluar`), amount, tanggal, description, attachment_path? (belum dipakai), deleted_at? |
| `invitations`, `invitation_uses` | kode undangan dan pemakaiannya |
| `activity_logs` | organization_id?, user_id?, action, subject_*, metadata JSON |
| `period_closures` | organization_id, period, closed_by?, closed_at; unik (organization_id, period). **Tabel dan relasi `Organization::periodClosures()` ada, belum dipakai (Langkah F2).** |

`is_billable` ≠ `is_active`. Banyak FK sengaja `restrict`; hapus organisasi lewat `Organization::purge()`.

## 5. Peran dan hak (Policy, sudah terpasang dan dipakai)

| Kemampuan | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: |
| `viewDashboard`, `viewTransactions`, `viewInvoices` (Anggota hanya tagihan miliknya) | ya | ya | ya |
| `viewReports` (laporan + unduh CSV) | ya | ya | – |
| `viewArrears` (bagian tunggakan di laporan) | ya | – | – |
| `manageTransactions`, `manageInvoices` (Admin + Editor) | ya | ya | – |
| `deleteTransactions`, `manageMembers`, `manageInvitations`, `manageSettings` | ya | – | – |

**Aturan tagihan sendiri:** tak seorang pun melunasi, menyetujui/menolak, atau membatalkan pelunasan tagihannya sendiri (`InvoiceController::isOwn()`); pengelola lain yang memverifikasi. Pemilik tagihan boleh mengajukan dan mengurus bukti tagihannya sendiri.

## 6. Fitur yang sudah ada (satu baris; rinci di `docs/RIWAYAT.md` bagian 5)

- **Auth:** Breeze, email wajib terverifikasi; kontak otomatis tertaut setelah verifikasi.
- **Organisasi/undangan:** buat, gabung dengan kode (hanya Anggota), kelola kode (Admin).
- **Anggota:** kelola peran Anggota↔Editor, nonaktifkan/aktifkan, `is_billable`, keluar.
- **Kategori dan transaksi:** CRUD kategori (Admin); transaksi dicatat Admin/Editor, hapus (soft) Admin; transaksi otomatis iuran **terkunci**; filter `month`, `type`, `q`; saldo dashboard = `opening_balance` + masuk − keluar.
- **Iuran (E1-E3):** buat tagihan bulanan, lunas tunai, batalkan pelunasan, ajukan pembayaran + bukti (privat), tarik, setujui/tolak. `Invoice::settle()` membuat transaksi pemasukan otomatis kategori Iuran, keterangan "Iuran {Bulan} {Tahun}" **tanpa nama anggota**.
- **Laporan (F1, `/laporan`):** filter bulan dan minggu (Senin-Minggu, dipotong di batas bulan); saldo awal/akhir periode, rincian per kategori, daftar transaksi; tunggakan hanya Admin (tidak mengikuti filter periode); unduh CSV (`/laporan/unduh`) dengan log `report.exported`. Tautan di dashboard.

## 7. Data demo dan pengujian

Seeder (`migrate:fresh --seed`), kata sandi semua akun `KitaDemo2026!`:

| Peran | Email | Ditagih |
| --- | --- | :-: |
| Owner/Dev | `owner@kaskita.demo` | – |
| Admin (Nadia) | `admin@kaskita.demo` | tidak |
| Editor (Rafi) | `editor@kaskita.demo` | tidak |
| Anggota (Dimas) | `anggota@kaskita.demo` | ya |
| Kontak tanpa akun | `sinta@`, `budi@`, `citra@` `kaskita.demo` | ya |

Organisasi **BEM Fakultas Teknologi**: saldo awal 500.000, iuran 25.000, kode `BEM26KAS`, 6 transaksi (2 otomatis dari iuran), 4 tagihan 2026-10 (Sinta lunas, Dimas belum, Budi menunggu dengan bukti fiktif, Citra lunas). Saldo dashboard Rp 455.000. Seeder tidak idempotent.

**Pengujian:** 184 tes lulus (SQLite di memori; MariaDB dan disk file sungguhan belum teruji). **Browser:** pemilik baru mencoba login, daftar, dashboard, halaman Transaksi, dan sebagian E2. **Belum dicoba di browser:** seluruh alur E3, **Laporan dan CSV (F1)**, Undangan, Anggota, Kategori, alur Transaksi lengkap. CSV belum dibuka di Excel/LibreOffice. **Uji mutasi:** hanya E1 satu kali; D, E2, E3, F1 belum (opsional).

## 8. Keputusan aktif dan masalah terbuka

**Keputusan (lengkap di arsip):**
- **D1** Editor: transaksi, laporan tanpa tunggakan, Iuran (kecuali tagihan sendiri).
- **D2** Anggota boleh melihat dashboard dan transaksi; keterangan transaksi iuran tanpa nama; tunggakan hanya Admin.
- **D3/D12** Admin/Editor yang ingin membayar diberi `is_billable=true`; seeder Nadia/Rafi dibiarkan tidak ditagih.
- **D5** serah-terima Admin, promosi Admin, hapus organisasi: belum ada; "minimal satu Admin" harus dirancang ulang bila dibuat.
- **D7** CSS prototipe vs Tailwind: belum diputuskan; tampilan mengikuti halaman asli KasKita. **D8** locale `id`/timezone `Asia/Jakarta`: belum diubah.
- **D10/D11** bukti bayar iuran: selesai (E3); nota transaksi manual menyusul (Langkah H).
- **D13** Laporan **CSV saja**; PDF ditunda (bukan dibatalkan; butuh `dompdf` dan ubah `composer.json`).
- **D14 (F1)** Tunggakan = semua tagihan `belum`+`menunggu` per hari ini, tidak ikut filter periode; minggu = Senin-Minggu dipotong batas bulan; CSV berpemisah koma.

**Masalah terbuka yang relevan:**
- Pencarian transaksi `q`: `%` dan `_` jadi wildcard (perbaikan: `addcslashes($q, '%_\\')`).
- **Zona waktu (D8):** pelunasan/persetujuan mengisi `tanggal` transaksi dengan hari ini (UTC), bisa bergeser dekat tengah malam WIB; bulan bawaan laporan juga memakai jam server. **Relevan untuk F2.**
- View Iuran: tagihan sendiri milik pengelola berstatus `belum` menampilkan "Menunggu diverifikasi pengelola lain" padahal belum diajukan (perbaikan kecil: tampilkan hanya bila `status === 'menunggu'`).
- "Lunas tunai" pada tagihan `menunggu` mengubah `method` jadi tunai dan bukti tetap tersimpan.
- Laporan: daftar transaksi periode tidak dipaginasi; CSV berkoma bisa terbuka dalam satu kolom di Excel berlokal Indonesia (bila terjadi, ganti ke titik koma, satu baris di `ReportController::download`).
- Non-anggota mendapat 403 untuk organisasi yang ada dan 404 untuk yang tidak ada (usul: 404 keduanya).
- `OwnerSeeder` memakai kata sandi tetap: jangan `db:seed` di server sungguhan.
- Konfigurasi bawaan masih Inggris/UTC; `README.md` basi; `welcome.blade.php` bawaan.
- `.env` pernah tertempel di percakapan: disarankan `php artisan key:generate` dan ganti kata sandi DB.
- Halaman Transaksi sempat tampak melebar tanpa gaya (kemungkinan `npm run dev` tidak jalan; belum dikonfirmasi).
- Daftar lengkap masalah ada di arsip bagian 9.

## 9. Langkah berikutnya (satu langkah per percakapan)

**Selesai:** A-E3 dan F1 (rincian di `docs/RIWAYAT.md`).

**Opsional sebelum F2 (bisa dilakukan pemilik sendiri):** coba di browser alur E3 (Dimas mengajukan dengan bukti → Rafi mengunduh dan menyetujui; ulangi dengan menolak) dan halaman Laporan (login Nadia dan Rafi: Nadia melihat tunggakan, Rafi tidak; unduh CSV lalu buka di Excel/LibreOffice). Perbaikan masalah view Iuran bisa jadi "langkah 0" kecil di awal F2.

### Langkah F2 — Tutup buku ← *berikutnya*
`PeriodClosure`: Admin menutup bulan; bulan ditutup menolak tambah/ubah/hapus transaksi di bulan itu **dan** pelunasan/persetujuan/pembatalan iuran yang membuat atau menghapus transaksi di periode tertutup (menyentuh `TransactionController`, `InvoiceController`, `Invoice::settle()`). Perlu satu pertanyaan desain di awal: transaksi otomatis iuran memakai tanggal hari ini, jadi bagaimana bila bulan berjalan sudah ditutup? Default aman: tolak dengan pesan jelas. Bisa dibuka kembali oleh Admin dengan catatan log. Pertimbangkan Policy baru (mis. `closePeriods`, Admin) dan tampilan status tutup di halaman Laporan.

**Sebelum menulis kode, minta pemilik menjalankan `git status --short; ls app/Models database/migrations tests/Feature`, lalu menempel:**
- `app/Models/PeriodClosure.php`, migrasi `period_closures`, `app/Http/Controllers/InvoiceController.php`, `app/Http/Requests/TransactionRequest.php`, `routes/web.php`, `app/Policies/OrganizationPolicy.php`. (`TransactionController`, `Invoice`, `Organization`, dan `ReportController` sudah pernah dilihat; minta versi terbaru hanya bila diperlukan.)

### Langkah G — Panel Owner/Dev
`/owner/*`: daftar organisasi (nama, tanggal dibuat, jumlah anggota, status), aktifkan/nonaktifkan; tes bahwa tidak menyentuh data keuangan.

### Langkah H — Tampilan dan penyempurnaan
View prototipe halaman demi halaman (putuskan D7), navigasi/sidebar, terjemahkan teks Breeze, atur D8, perbarui `README.md`, hapus `welcome.blade.php`, syarat lingkungan `OwnerSeeder`, uji di MariaDB, nota transaksi (D10), Pull Request ke `main` (perhatikan GitHub Pages), tinjau masalah terbuka.