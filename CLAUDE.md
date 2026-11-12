# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude, **versi ringkas**. Fokus: aturan kerja dan langkah berikutnya.
> Detail langkah yang sudah selesai (aturan bisnis rinci, daftar tes, riwayat) ada di **`docs/RIWAYAT.md`**. Jangan ditempel utuh; minta bagian tertentu bila perlu.
> Terakhir diperbarui: **12 Nov 2026** · Tes: **220 lulus** (dijalankan pemilik) · Laravel 13 + Breeze (Blade).

## 0. Cara kerja dan aturan

Pemilik memakai **beberapa sesi Claude** karena batas pemakaian. Pemilik **bukan programmer mahir** (vibecoder): jelaskan perintah dengan jelas, beri satu kalimat "apa yang diubah dan kenapa" di tiap serahan kode.

**Cara serah kode:** Claude menulis kode **di chat, per file, lengkap**; pemilik menyalin. Tidak ada zip/unggahan. Claude tidak melihat repo dan tidak bisa menjalankan tes; ia hanya tahu yang ditempel pemilik. Pemilik menempel dengan `cat`, per kelompok kecil (2-3 file).

Aturan:

1. **Mulai selalu dengan `git status --short` dan `ls`** untuk memastikan belum ada kode yang ditulis sesi lain (pernah terjadi di E3 dan di awal H). Kode di repo lebih benar daripada berkas ini.
2. **Jangan mengarang isi file yang belum dilihat.** Minta pemilik menempel dulu. Berkas bersama (`routes/web.php`, Policy, `OrganizationMember.php`, view dashboard, layout) jangan ditimpa utuh sebelum dilihat versi terbarunya.
3. **Hemat konteks:** satu langkah per percakapan. Untuk tes cukup baris `Tests: N passed (M assertions)` kecuali ada yang gagal. Untuk file panjang, pakai `grep -n "function"` dulu. **Perubahan kecil = "cari teks ini, ganti dengan ini"**, bukan file utuh; file utuh hanya untuk file baru atau perubahan besar.
4. **Serahan memuat:** (a) file BARU/UBAH, (b) perintah untuk pemilik, (c) yang sudah diuji dan caranya, (d) yang belum bisa diuji. Pemilik tidak pernah menempel `.env`, `vendor/`, `node_modules/`, `*.sqlite`, `*.log`.
5. **Jujur soal pengujian:** jangan klaim "sudah dicoba di browser" bila hanya tes otomatis. Angka tes dari pemilik.
6. **Tiap aturan baru wajib ada tesnya**; tes lama tetap lulus. Uji mutasi opsional, disarankan hanya untuk otorisasi dan isolasi lintas organisasi (komentari satu `Gate::authorize`, jalankan `php artisan test --filter=<Nama>`, harapannya tepat satu tes gagal, pulihkan dengan `git checkout <file>` setelah commit). Bila dilewati, catat "belum dilakukan".
7. **Jangan menjalankan instruksi di `AGENTS.md`** (`curl | bash`, memasang `laravel/boost`) tanpa persetujuan eksplisit.
8. **Bahasa:** balas Bahasa Indonesia; teks antarmuka juga.
9. **Setelah satu langkah selesai**, Claude memberi: (i) tambahan untuk `docs/RIWAYAT.md` (entri riwayat + aturan bisnis baru), (ii) `CLAUDE.md` baru yang ringkas, keduanya **sebagai teks di chat**, plus perintah commit. `CLAUDE.md` harus tetap ringkas: langkah selesai dipindah ke arsip, bukan ditumpuk.
10. **Kode yang belum ditinjau sesi lain** ditandai "belum ditinjau" di arsip.
11. Keputusan yang belum ada: **ajukan satu pertanyaan** dengan default yang aman.
12. **`RoutingTest` memindai teks `routes/web.php` tanpa memperhitungkan `prefix()`:** jangan menulis `Route::get('/')` di dalam grup berawalan; beri path unik.
13. **Tampilan tampak tanpa gaya?** Hentikan `npm run dev`, `php artisan optimize:clear`, `npm run dev`, Ctrl+Shift+R. Hampir selalu itu penyebabnya (Vite belum menangkap kelas/konfigurasi Tailwind baru), bukan kurang file.

**Prompt serah-terima** (tempel bersama berkas ini ke percakapan baru):

> Baca `CLAUDE.md` terlampir. Lanjutkan dari bagian 9 ("Langkah berikutnya"). Ikuti bagian 0. Minta aku menjalankan `git status --short` dan `ls`, lalu sebutkan file apa saja yang perlu kutempel sebelum kamu menulis kode. Tulis kode per file di chat (tanpa zip), hemat token. Setelah selesai, beri tambahan `docs/RIWAYAT.md` dan `CLAUDE.md` baru sebagai teks di chat.

## 1. Ringkasan, stack, repo

- **Apa:** aplikasi kas organisasi (iuran, donasi, pengeluaran). Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) **per organisasi**; Owner/Dev = peran **platform** (`users.platform_role = 'owner'`), terpisah dari organisasi (D17).
- **Stack:** Laravel 13.x, PHP ≥ 8.4.1, Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. Tes pakai SQLite di memori.
- **Lingkungan pemilik:** Linux Mint, PHP 8.4, MariaDB 10.11 (db `kaskita`, user `kaskita_user`), Node 22 (nvm). Dua terminal: `npm run dev` dan `php artisan serve` (`http://127.0.0.1:8000`). Periksa DB dengan `php artisan db:show`, `db:table <tabel>`, `tinker`.
- **Repo:** `https://github.com/AzkalGit/KasKita`, folder `~/Documents/Kuliah/sem3/SI/KasKita` (Laravel di akar). Branch `main` = prototipe statis (GitHub Pages), **jangan disentuh**. Kerja di **`fitur/laravel-fondasi`**. Prototipe lama di `prototipe-statis/` (berisi `dashboard.html`, `login.html`, `register.html`, `transaksi.html`, `iuran.html`, `anggota.html`, `laporan.html`, `pengaturan.html`, `organisasi.html`, `owner.html`, `index.html`, folder `css/` dan `js/`). Sebelum `git add -A`, cek `git status` (jangan sampai `.env` ikut).

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
- **Organisasi aktif ditentukan URL.** Rute organisasi di grup `org.member` dengan `{organization}` dan `->scopeBindings()` (ID organisasi lain = 404). Pengecualian `{invoice}`: dicari lewat `Invoice::inOrganization()` + `->whereNumber('invoice')`, karena tagihan tak punya `organization_id`. `org.member` juga menolak (403) organisasi `nonaktif`.
- **Rute platform (Owner)** di grup `owner` (`/owner/organisasi/...`), bukan `org.member`; jangan baca data keuangan di sana.
- **Otorisasi:** `Gate::authorize(...)` di controller, `@can` di view, `FormRequest::authorize()` untuk aksi berinput. Peran lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_*`. Owner: middleware `owner` + `User::isOwner()`.
- **Log aktivitas wajib lewat `ActivityLogger::record()`.**
- Validasi di FormRequest (di controller `index`/laporan boleh `$request->validate()` untuk filter GET), otorisasi di Policy. Jangan taruh rute aplikasi di `routes/auth.php`. Jalankan `pint` pada file yang diubah.
- Jangan ubah migrasi lama tanpa memberi tahu pemilik; untuk skema baru pertimbangkan migrasi baru. **Jangan ubah `composer.json` (D13: tanpa paket PDF).**
- **Lampiran** di disk privat (`Invoice::PROOF_DISK` = `local`), tidak pernah disk `public`; tipe dan ukuran divalidasi di server; unduh lewat rute yang dicek hak.
- **CSV:** `fputcsv(..., ',', '"', '')`, BOM UTF-8, netralkan nilai berawalan `= + - @`.
- Jangan menyimpan rahasia di repo, seeder, atau percakapan. Jangan tampilkan data keuangan di panel Owner/Dev.
- **Tampilan (D7 = Tailwind di Blade):** warna dan font prototipe ada di `tailwind.config.js` (`deep`, `brand`, `mint`, `soft`, `canvas`, `ink`, `mute`, `line`, `expense`, `income`; font Plus Jakarta Sans). Pakai token ini, bukan warna Breeze (indigo/gray). Tanpa paket JS/CSS baru; grafik dengan CSS murni. Kotak/kata sandi demo hanya bila `app()->environment('local')`.

## 3. Struktur (ringkas)

```
app/Http/Controllers/   Organization, OrganizationDashboard, Invitation, Member, Category, Transaction, Invoice, Report, PeriodClosure, OwnerOrganization, Profile, Auth/*
app/Http/Requests/ClosePeriodRequest      Store/Update* per modul, TransactionRequest, CategoryRequest, SubmitPaymentRequest, ProofRequest, RejectInvoiceRequest
app/Support/ActivityLogger.php
app/Http/Middleware/    EnsureOrganizationMember (org.member), EnsureOwner (owner)
app/Exceptions/PeriodClosedException.php
app/Listeners/LinkPendingMemberships.php ; app/Policies/OrganizationPolicy.php
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice, Invitation, InvitationUse, ActivityLog, PeriodClosure
database/seeders/       OwnerSeeder, DemoOrganizationSeeder
resources/views/        organizations, invitations, members, categories, transactions, invoices, reports, owner/organizations, layouts (app, guest, sidebar, topbar, navigation), components (side-link, bawaan Breeze)
routes/web.php          rute aplikasi
tests/Feature/         satu berkas per modul (mis. ReportTest, OwnerPanelTest, LoginPageTest)
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
| `period_closures` | organization_id, period, closed_by?, closed_at; unik (organization_id, period). Ada baris = bulan tertutup (F2). |

`is_billable` ≠ `is_active`. Banyak FK sengaja `restrict`; hapus organisasi lewat `Organization::purge()`.

## 5. Peran dan hak (Policy, sudah terpasang dan dipakai)

| Kemampuan | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: |
| `viewDashboard`, `viewTransactions`, `viewInvoices` (Anggota hanya tagihan miliknya) | ya | ya | ya |
| `viewReports` (laporan + unduh CSV) | ya | ya | – |
| `viewArrears` (bagian tunggakan di laporan) | ya | – | – |
| `manageTransactions`, `manageInvoices` (Admin + Editor) | ya | ya | – |
| `deleteTransactions`, `manageMembers`, `manageInvitations`, `manageSettings`, `closePeriods` | ya | – | – |

**Aturan tagihan sendiri:** tak seorang pun melunasi, menyetujui/menolak, atau membatalkan pelunasan tagihannya sendiri (`InvoiceController::isOwn()`); pengelola lain yang memverifikasi. Pemilik tagihan boleh mengajukan dan mengurus bukti tagihannya sendiri. Di view Iuran, pesan "Menunggu diverifikasi pengelola lain" hanya untuk status `menunggu`.

**Owner platform (bukan peran organisasi):** hanya panel `/owner/organisasi/*` (middleware `owner`); tidak membuat/bergabung organisasi (D17).

## 6. Fitur yang sudah ada (satu baris; rinci di `docs/RIWAYAT.md`)

- **Auth:** Breeze, email wajib terverifikasi; kontak otomatis tertaut setelah verifikasi.
- **Organisasi/undangan:** buat, gabung dengan kode (hanya Anggota), kelola kode (Admin).
- **Anggota:** kelola peran Anggota↔Editor, nonaktifkan/aktifkan, `is_billable`, keluar.
- **Kategori dan transaksi:** CRUD kategori (Admin); transaksi dicatat Admin/Editor, hapus (soft) Admin; transaksi otomatis iuran **terkunci**; filter `month`, `type`, `q`; saldo dashboard = `opening_balance` + masuk − keluar.
- **Iuran (E1-E3):** buat tagihan bulanan, lunas tunai, batalkan pelunasan, ajukan pembayaran + bukti (privat), tarik, setujui/tolak. `Invoice::settle()` membuat transaksi pemasukan otomatis kategori Iuran, keterangan "Iuran {Bulan} {Tahun}" **tanpa nama anggota**.
- **Laporan (F1, `/laporan`):** filter bulan dan minggu; saldo awal/akhir, rincian per kategori, daftar transaksi; tunggakan hanya Admin; unduh CSV dengan log `report.exported`.
- **Tutup buku (F2):** Admin menutup/membuka bulan lampau dari halaman Laporan, dilog. Bulan tertutup menolak tambah/ubah/pindah/hapus transaksi dan beberapa aksi iuran (`PeriodClosedException`); Anggota tetap boleh mengajukan pembayaran.
- **Panel Owner (G, `/owner/organisasi/daftar`):** hanya metadata organisasi; nonaktifkan/aktifkan dengan log; organisasi nonaktif = 403 bagi anggotanya.
- **Tampilan (H, sebagian):** layout sidebar hijau tua untuk halaman organisasi (`layouts/sidebar`, responsif) dan topbar untuk halaman lain; login dua kolom (`layouts/guest` + `auth/login`, kotak demo hanya di `local`).

## 7. Data demo dan pengujian

Seeder (`migrate:fresh --seed`), kata sandi semua akun `KitaDemo2026!`:

| Peran | Email | Ditagih |
| --- | --- | :-: |
| Owner/Dev | `owner@kaskita.demo` | – |
| Admin (Nadia) | `admin@kaskita.demo` | tidak |
| Editor (Rafi) | `editor@kaskita.demo` | tidak |
| Anggota (Dimas) | `anggota@kaskita.demo` | ya |
| Kontak tanpa akun | `sinta@`, `budi@`, `citra@` `kaskita.demo` | ya |

Organisasi **BEM Fakultas Teknologi**: saldo awal 500.000, iuran 25.000, kode `BEM26KAS`, 6 transaksi (2 otomatis dari iuran), 4 tagihan 2026-10 (Sinta lunas, Dimas belum, Budi menunggu dengan bukti fiktif, Citra lunas). Saldo dashboard Rp 455.000 pada data seeder murni (DB lokal pemilik sudah berisi data tambahan). Seeder tidak idempotent.

**Pengujian:** 220 tes lulus (SQLite di memori; MariaDB dan disk file sungguhan belum teruji). **Browser:** pemilik sudah mencoba login, daftar, dashboard (tampil dengan sidebar baru), halaman Transaksi, sebagian E2, daftar Panel Owner, halaman login baru. **Belum dicoba di browser:** alur E3, Laporan dan CSV, Tutup buku, Undangan, Anggota, Kategori, alur Transaksi lengkap, pengalihan Owner dari `/organisasi`, tombol Panel Owner, tampilan `/register` dan `/forgot-password` setelah layout dua kolom. CSV belum dibuka di Excel/LibreOffice. **Uji mutasi:** hanya E1 satu kali (opsional; paling berguna: otorisasi F2 dan `EnsureOwner`).

## 8. Keputusan aktif dan masalah terbuka

**Keputusan (lengkap di arsip):**
- **D1** Editor: transaksi, laporan tanpa tunggakan, Iuran (kecuali tagihan sendiri).
- **D2** Anggota boleh melihat dashboard dan transaksi; keterangan transaksi iuran tanpa nama; tunggakan hanya Admin. (Prototipe menulis "Iuran Oktober — Alya Nurdin": **jangan disalin**.)
- **D3/D12** Admin/Editor yang ingin membayar diberi `is_billable=true`.
- **D5** serah-terima Admin, promosi Admin, hapus organisasi: belum ada.
- **D7 (diputuskan)** Tailwind di Blade dengan token warna prototipe. **D8** locale `id`/timezone `Asia/Jakarta`: belum diubah.
- **D10/D11** bukti bayar iuran selesai (E3); nota transaksi manual menyusul.
- **D13** Laporan CSV saja. **D14** Tunggakan = semua tagihan `belum`+`menunggu` per hari ini. **D15** Tutup buku hanya bulan lampau, hanya Admin, bisa dibuka lagi.
- **D16** Tanpa enkripsi end-to-end; perlindungan: hash, lampiran privat, panel Owner tanpa data keuangan.
- **D17** Owner platform terpisah dari peran organisasi.

**Masalah terbuka yang relevan:**
- Isi halaman organisasi (Dashboard, Transaksi, Iuran, Anggota, Laporan, Kategori, Undangan, Organisasi, Profil, Panel Owner) masih gaya Breeze lama (link indigo, kartu putih); belum seragam dengan prototipe.
- Halaman Daftar, Lupa/Reset kata sandi: layout sudah dua kolom tetapi isi masih Inggris/Breeze.
- Sidebar belum punya ikon dan badge angka merah di menu Iuran (jumlah tagihan `menunggu`); menu Anggota hanya Admin.
- Pencarian transaksi `q`: `%` dan `_` jadi wildcard (perbaikan: `addcslashes($q, '%_\\')`).
- **Zona waktu (D8):** `tanggal` pelunasan memakai hari ini (UTC); bulan bawaan laporan dan F2 memakai jam server.
- "Lunas tunai" pada tagihan `menunggu` mengubah `method` jadi tunai dan bukti tetap tersimpan.
- Laporan: daftar transaksi tidak dipaginasi; CSV berkoma bisa terbuka dalam satu kolom di Excel berlokal Indonesia.
- Non-anggota mendapat 403 untuk organisasi yang ada dan 404 untuk yang tidak ada (usul: 404 keduanya).
- Bila Admin menambahkan email Owner sebagai kontak, akun Owner bisa tertaut saat verifikasi (celah kecil, dibiarkan).
- `OwnerSeeder` memakai kata sandi tetap: jangan `db:seed` di server sungguhan.
- `README.md` basi; `welcome.blade.php` bawaan; `.env` pernah tertempel di percakapan (saran: `php artisan key:generate`, ganti kata sandi DB).

## 9. Langkah berikutnya (satu langkah per percakapan)

**Selesai:** A-G, dan bagian awal H (layout sidebar/topbar, login, perbaikan pesan tagihan).

### Langkah H — Tampilan dan penyempurnaan (dikerjakan halaman demi halaman)

**Berikutnya: Dashboard** (`resources/views/organizations/dashboard.blade.php`, `OrganizationDashboardController`). Selisih dengan prototipe: judul "Dashboard" + subjudul; tombol "+ Catat transaksi" (bila `manageTransactions`); banner kuning "N bukti pembayaran iuran menunggu verifikasi →" (hanya pengelola, tautan ke Iuran); 4 kartu (saldo hijau tua dengan "termasuk saldo awal Rp ...", pemasukan, pengeluaran, jumlah transaksi); grafik batang pemasukan vs pengeluaran per bulan (CSS murni, tanpa paket baru) di kartu kiri; "Transaksi terakhir" di kartu kanan, nominal tebal berwarna (`text-income`/`text-expense`), **tanpa nama anggota (D2)**; hapus link "← Kembali ke daftar organisasi" (sudah di sidebar). **Filter periode ditunda.** Badge angka merah menu Iuran di sidebar ikut dikerjakan (data sama dengan banner; hitung hanya untuk pengelola). Semua butuh tambahan di controller + tes.
Tempel sebelum menulis kode: `cat prototipe-statis/dashboard.html`, `ls -la prototipe-statis/css prototipe-statis/js`, `cat app/Http/Controllers/OrganizationDashboardController.php`, `cat resources/views/organizations/dashboard.blade.php`; bagian CSS prototipe diminta lewat `grep -n` pada `prototipe-statis/css/*.css`.

**Urutan halaman sesudahnya** (satu per percakapan): Daftar + Lupa/Reset kata sandi (terjemahkan teks Breeze) → Transaksi → Iuran (+ perbaiki sisa gaya) → Anggota → Laporan (+ Tutup buku) → Pengaturan (Kategori/Undangan) → Organisasi (daftar/buat/gabung) → Profil → Panel Owner (periksa tampilan tanpa gaya) → ikon sidebar.
**Terakhir:** atur D8 (locale/timezone), perbarui `README.md`, hapus `welcome.blade.php`, syarat lingkungan `OwnerSeeder`, uji di MariaDB, nota transaksi (D10), Pull Request ke `main` (perhatikan GitHub Pages), tinjau masalah terbuka.
EOF