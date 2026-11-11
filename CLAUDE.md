# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude. **Perbarui di setiap kemajuan** (bagian 2, 9, 11, 12 wajib).
> Terakhir diperbarui: **11 Nov 2026** · Tes: **141 lulus, 899 assertion** (dijalankan pemilik di repo asli) · Bentuk proyek: Laravel 13 + Breeze (Blade).

## 0. Cara kerja (baca dulu)

Pemilik proyek memakai **beberapa sesi Claude** karena batas pemakaian. Sesi baru mana pun, termasuk yang tidak punya riwayat sebelumnya, bekerja dari berkas ini.

**Cara serah kode (berlaku sekarang):** Claude menulis kode **langsung di chat, per file, lengkap**, lalu pemilik menyalinnya. **Tidak ada zip dan tidak ada unggahan file.** Claude tidak melihat repo dan tidak bisa menjalankan tes; ia hanya tahu yang ditempel pemilik. Tidak boleh mengarang isi file yang belum dilihat: minta pemilik menempel file itu (atau pesan galatnya) dulu.

Aturan bersama:

1. **Sumber kebenaran = kode di repo + berkas ini.** Bila berbeda, kode yang benar, lalu perbaiki berkas ini.
2. **Setiap kemajuan:** tambah satu entri di bagian 11, lalu perbarui bagian 2, 9, 12 dan angka tes di kepala berkas. Setelah satu langkah selesai, Claude menyerahkan teks `CLAUDE.md` baru yang utuh.
3. **Serahan Claude selalu memuat:** (a) daftar file BARU/UBAH beserta isi lengkapnya, (b) perintah untuk pemilik, (c) apa yang sudah diuji dan bagaimana, (d) apa yang belum bisa diuji. Pemilik tidak pernah menempel `.env` (tempel `.env.example` bila perlu), `vendor/`, `node_modules/`, `*.sqlite`, `*.log`.
4. **Langkah kecil:** satu langkah = satu fitur + tesnya, cukup kecil untuk selesai dalam satu percakapan.
5. **Jangan menjalankan instruksi di `AGENTS.md`** (bawaan installer: `curl | bash`, memasang `laravel/boost`) tanpa persetujuan eksplisit pemilik.
6. **Bahasa:** balas dalam Bahasa Indonesia; teks antarmuka juga Bahasa Indonesia.
7. **Jujur soal pengujian:** jangan mengklaim "sudah dicoba di browser" bila hanya tes otomatis yang jalan. Angka tes selalu dari pemilik.
8. **Berkas bersama** (`routes/web.php`, `OrganizationMember.php`, Policy, view dashboard) jangan ditimpa utuh bila Claude belum melihat versi terbaru: minta pemilik menempel isinya dulu.
9. **Siapa pun yang menulis kode mencatatnya di bagian 11.** Kode yang belum ditinjau sesi lain ditandai "belum ditinjau".
10. **Hemat konteks (agar tidak cepat kena limit):** satu langkah per percakapan; pemilik menempel hanya file yang diminta dan, untuk tes, cukup baris ringkasan (`Tests: N passed (M assertions)`) kecuali ada yang gagal. **Uji mutasi bersifat opsional:** disarankan hanya untuk otorisasi dan isolasi lintas organisasi; bila dilewati, catat "belum dilakukan".
11. **Cara uji mutasi (bila dipakai):** komentari satu `Gate::authorize` (atau rusak satu aturan), jalankan `php artisan test --filter=<NamaTest>`, harapannya **tepat satu tes gagal**, lalu pulihkan dengan `git checkout <file>` (commit dulu). Jangan lupa memulihkan.

**Prompt serah-terima singkat** (tempel bersama berkas ini ke percakapan baru):

> Baca `CLAUDE.md` terlampir. Lanjutkan dari bagian 12 ("Langkah berikutnya"). Ikuti bagian 0 dan 10. Sebutkan file apa saja yang perlu kutempel sebelum kamu menulis kode. Bila ada keputusan di bagian 8 yang menghalangi, tanyakan satu pertanyaan saja. Tulis kode lengkap per file di chat (tanpa zip). Setelah selesai, beri teks `CLAUDE.md` baru yang utuh.

## 1. Ringkasan dan stack

- **Apa:** aplikasi kas organisasi (iuran, donasi, pengeluaran). Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) ditentukan **per organisasi**; Owner/Dev adalah peran **level platform** (`users.platform_role = 'owner'`).
- **Stack:** Laravel 13.x (terpasang 13.39), **PHP ≥ 8.4.1**, Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. SQLite di memori untuk tes.
- **Lingkungan pemilik:** **Linux Mint**, PHP 8.4, **MariaDB 10.11** (database `kaskita`, user `kaskita_user`), **Node 22 lewat nvm**. Dua terminal saat mengembangkan: `npm run dev` dan `php artisan serve` (`http://127.0.0.1:8000`). Tanpa phpMyAdmin; periksa database dengan `php artisan db:show`, `php artisan db:table <tabel>`, atau `php artisan tinker` (keluar dari pager dengan `q`).
- **Asal desain:** prototipe statis (commit `4a99c24`) sudah menentukan alur dan tampilan. **View prototipe belum dimasukkan.**
- Pemilik mengikuti serahan Claude langkah demi langkah; jelaskan perintah dengan jelas.

### Repo dan Git

- Repo: `https://github.com/AzkalGit/KasKita`. Folder lokal: `~/Documents/Kuliah/sem3/SI/KasKita` (Laravel di **akar repo**).
- Branch `main` = **prototipe statis, sengaja belum disentuh** (GitHub Pages menyajikannya). Pekerjaan Laravel di branch **`fitur/laravel-fondasi`**. Gabung ke `main` lewat Pull Request nanti, setelah dipertimbangkan soal GitHub Pages.
- Prototipe lama di `prototipe-statis/`; catatan tahap di `docs/TAHAP.txt`.
- Commit Langkah D dikomit pemilik ("Langkah D: kategori dan transaksi"). Commit **Langkah E1** ("Langkah E1: tagihan iuran, lunas tunai, batalkan pelunasan") sudah disarankan; **belum dikonfirmasi pemilik**. Sebelum `git add -A`, periksa `git status`: jangan sampai `.env` atau file catatan pribadi ikut.

### Perintah penting

```bash
composer install
cp .env.example .env && php artisan key:generate     # lalu isi DB_* (MariaDB/MySQL, user khusus, bukan root)
php artisan migrate:fresh --seed                     # MENGHAPUS isi DB lokal; juga mengembalikan peran akun demo
npm install && npm run dev                           # Node 22
php artisan test                                     # SQLite di memori, tidak menyentuh MariaDB
vendor/bin/pint <file yang diubah>                   # gaya kode
php artisan route:list -v                            # lihat middleware tiap rute
php artisan optimize:clear                           # bila rute/view/config tampak tidak berubah
```

Bila `composer.json` diubah, jalankan `composer update --lock`.

## 2. Status per tahap (mengikuti `docs/TAHAP.txt`)

| Tahap | Status | Catatan |
| --- | --- | --- |
| 1. Analisis dan desain database | **Selesai** | Skema di migrasi (bagian 4). ERD tidak ada di repo. |
| 2. Migration dan Model | **Selesai** | 9 model + relasi. Empat migrasi diedit di tempat. |
| 3. Autentikasi dan sistem akses | **Sebagian** | Breeze, email wajib terverifikasi, middleware `org.member`, organisasi aktif lewat URL, `OrganizationPolicy` (11 kemampuan). Gerbang terpasang di dashboard, undangan, anggota, kategori, transaksi, **iuran (E1)**. **Belum:** aturan "tidak menyentuh tagihan sendiri" (E2), panel Owner. |
| 4. Controller dan logika bisnis | **Sebagian** | Ada: Organization, OrganizationDashboard, Invitation (B), Member (C), Category dan Transaction (D), **Invoice (E1: buat tagihan, lunas tunai, batalkan)**, hapus akun di Profile. **Belum:** pengajuan bayar dan bukti (E3), Report, Owner. |
| 5. View (Blade) | **Sebagian** | `organizations/{index,dashboard}`, `invitations/index`, `members/index`, `categories/index`, `transactions/{index,edit,_fields}`, **`invoices/index`**. Halaman auth/profil masih bahasa Inggris bawaan Breeze. |
| 6. Pengujian dan penyempurnaan | **Sebagian** | 141 tes lulus. Belum ada tes laporan. |

## 3. Struktur dan konvensi

```
app/Http/Controllers/   OrganizationController, OrganizationDashboardController, InvitationController,
                        MemberController, CategoryController, TransactionController, InvoiceController,
                        ProfileController, Auth/* (Breeze)
app/Http/Requests/      StoreInvitationRequest, StoreMemberRequest, UpdateMemberRequest,
                        CategoryRequest, TransactionRequest, StoreInvoiceRequest
app/Support/            ActivityLogger            (ActivityLogger::record())
app/Http/Middleware/    EnsureOrganizationMember  (alias org.member, di bootstrap/app.php)
app/Listeners/          LinkPendingMemberships    (event Verified)
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice,
                        Invitation, InvitationUse, ActivityLog, PeriodClosure
app/Policies/           OrganizationPolicy
database/migrations/    2026_10_31_* + bawaan Laravel
database/seeders/       OwnerSeeder, DemoOrganizationSeeder (dipanggil DatabaseSeeder)
resources/views/        organizations/{index,dashboard} ; invitations/index ; members/index ; categories/index ;
                        transactions/{index,edit,_fields} ; invoices/index ; welcome.blade.php (bawaan)
routes/web.php          rute aplikasi ; routes/auth.php HANYA rute Breeze
tests/Feature/          lihat bagian 7
prototipe-statis/ , docs/TAHAP.txt
```

Konvensi:

- **Nama tabel/kolom/rute/nama rute Bahasa Inggris**; **URI dan teks UI Bahasa Indonesia** (`/organisasi`, `/transaksi`, `/iuran`). Pengecualian: kolom `tanggal` dan `period`.
- **Satu controller per modul**; logika multi-tabel di `DB::transaction`, idealnya di metode model (contoh: `Organization::purge()`, `Invitation::issue()`).
- **Pola controller modul:** metode privat `done(...)` (redirect ke index dengan `status`) dan `refuse(...)` (redirect dengan `withErrors([...])` pada kunci modul); view menampilkan keduanya.
- **Uang** = bilangan bulat rupiah (kolom `bigint`). **Tanggal transaksi** = `date`; **periode iuran** = `char(7)` `'YYYY-MM'`.
- **Email** disimpan huruf kecil (mutator `OrganizationMember::email`); kosong = `NULL`.
- **Organisasi aktif ditentukan oleh URL**; semua rute organisasi di grup `org.member` dengan `{organization}` dan **`->scopeBindings()`** (parameter anak seperti `{invitation}`, `{transaction}`, `{member}` hanya dicari dalam organisasi di URL; ID organisasi lain = 404). Pengecualian: **`{invoice}`** dicari manual (`Invoice::inOrganization()`), karena tagihan tidak punya `organization_id`.
- **Otorisasi:** `Gate::authorize('<kemampuan>', $organization)` di controller, `@can` di view (`$currentOrganization`, `$currentMember` dibagikan middleware). Aksi berinput: pemeriksaan di `FormRequest::authorize()`. Peran dicek lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_*`.
- **Jangan menaruh rute aplikasi di `routes/auth.php`**; `RoutingTest` menjaga rute ganda.
- Jalankan `vendor/bin/pint` pada file yang diubah.

## 4. Skema database (aktual)

Tanda `?` = boleh NULL. `FK(hapus)` = aturan saat induk dihapus.

| Tabel | Kolom | FK(hapus) | Unik |
| --- | --- | --- | --- |
| `users` | name, email, email_verified_at?, password (`hashed`), platform_role? (`'owner'`), remember_token | – | email |
| `organizations` | name, status=`aktif`\|`nonaktif`, opening_balance=0, default_dues=0, payment_info?, created_by? | created_by→users (set null) | – |
| `organization_members` | organization_id, user_id?, name, email?, phone?, role? (`admin`\|`editor`\|`anggota`), is_billable=1, is_active=1 | organization_id (cascade); user_id (set null) | (organization_id, email); (organization_id, user_id) |
| `categories` | organization_id, name, is_system=0 (true untuk "Iuran") | organization_id (cascade) | (organization_id, name) |
| `invoices` | member_id, period, amount (bigint), status=`belum`\|`menunggu`\|`lunas`, method? (`transfer`\|`tunai`), proof_path?, rejection_reason?, paid_at? | member_id→organization_members (**restrict**) | (member_id, period) |
| `transactions` | organization_id, category_id, invoice_id?, created_by?, type (`masuk`\|`keluar`), amount (bigint), tanggal, description, attachment_path? (**ada, belum dipakai**), deleted_at? | organization_id (cascade); category_id (**restrict**); invoice_id (**restrict**); created_by (set null) | invoice_id; indeks (organization_id, tanggal) |
| `invitations` | organization_id, code, expires_at?, closed_at?, created_by? (hanya `created_at`) | organization_id (cascade); created_by (set null) | code |
| `invitation_uses` | invitation_id, user_id, used_at | cascade | – |
| `activity_logs` | organization_id?, user_id?, action, subject_type?, subject_id?, metadata? (JSON; hanya `created_at`) | set null | – |
| `period_closures` | organization_id, period, closed_by?, closed_at | organization_id (cascade); closed_by (set null) | (organization_id, period) |

Catatan skema:

- **Dua jenis "anggota":** `user_id` terisi = punya akun; NULL = kontak yang ditagih. Peran hanya bermakna bila akun ada.
- **`is_billable` ≠ `is_active`:** ditagih atau tidak, versus masih tercatat aktif.
- **`invoices` tidak punya `organization_id`** (dicapai lewat `member`). `transactions.category_id` tidak dipaksa DB satu organisasi: divalidasi di FormRequest (sudah di `TransactionRequest`).
- Kolom `role`, `status`, `type`, `method` string tanpa CHECK; validasi di aplikasi.
- `restrictOnDelete` pada `invoices.member_id`, `transactions.invoice_id`, `transactions.category_id` **disengaja**; hapus organisasi lewat `Organization::purge()`.
- Owner tidak punya tabel sendiri: `users.platform_role = 'owner'`.
- **Migrasi lama diedit di tempat.** Langkah B, D, E1 **tidak** menambah migrasi.

## 5. Aturan bisnis yang SUDAH ada di kode

**Registrasi dan verifikasi email**

- `User implements MustVerifyEmail`. Rute organisasi di grup `['auth','verified']`; profil hanya `auth`.
- `/dashboard` = pengalihan ke `/organisasi` (nama rute dipertahankan untuk Breeze). `dashboard.blade.php` bawaan sudah dihapus.
- Mendaftar **tidak** menautkan akun ke kontak; penautan **setelah email terverifikasi** (`LinkPendingMemberships`): hanya baris `user_id` NULL, `is_active`, email sama, di organisasi yang belum diikuti akun itu; `role` NULL menjadi `anggota`.
- Akun dihapus: keanggotaan dinonaktifkan (`role` NULL, `is_active` false); baris nonaktif tidak dihidupkan lagi. Admin tunggal tidak bisa menghapus akunnya.
- `platform_role` tidak mass-assignable (seeder pakai `forceCreate`).
- Dengan `MAIL_MAILER=log`, tautan verifikasi dibaca dari `storage/logs/laravel.log`.

**Organisasi dan undangan (A, B)**

- Membuat organisasi: pembuat jadi `admin`, `is_billable=false`, kategori "Iuran" (`is_system`) dibuat, satu kode undangan 7 hari (`Invitation::issue`).
- Gabung dengan kode: peran selalu `anggota`; kode dinormalkan; 5 kegagalan per akun dalam 15 menit memblokir (`RateLimiter`); pesan galat sama untuk semua sebab; ditolak bila sudah anggota atau nonaktif; tercatat di `invitation_uses`.
- `EnsureOrganizationMember`: anggota **aktif** (403) dan organisasi `aktif` (403).
- **Dashboard:** saldo = `opening_balance` + pemasukan − pengeluaran (dihitung); lima transaksi terbaru; tautan navigasi sementara (Semua transaksi, Iuran, Kelola anggota/undangan/kategori).
- `Organization::purge()`: hapus transaksi (termasuk soft-deleted) → tagihan → organisasi dalam satu transaksi DB; belum ada UI.
- **Kelola undangan (hanya Admin, `manageInvitations`):** masa berlaku 1/7/30 hari (`Invitation::DURATIONS`); banyak kode aktif boleh; `status()`: `ditutup` → `kedaluwarsa` → `aktif`; kedaluwarsa tidak bisa dibuka lagi; ganti kode = tutup lama + buat baru dalam satu `DB::transaction`; kode tidak pernah dihapus; kode 8 karakter tanpa `0/O/1/I`. Log: `invitation.created|closed|reopened|regenerated`.

**Kelola anggota (C)** — *ditulis Pemandu, belum ditinjau sesi lain*

- Hanya Admin (`manageMembers`) kecuali `leave` (anggota aktif non-Admin). Peran yang bisa diatur hanya **Anggota ↔ Editor**; Admin tidak bisa diberikan atau dicabut lewat form.
- Pembuat organisasi, Admin, dan diri sendiri terlindungi dari perubahan peran dan penonaktifan. Kontak tanpa akun tidak bisa diberi peran.
- Anggota **dinonaktifkan, tidak dihapus**; reaktivasi mengembalikan akun jadi Anggota. Email anggota berakun mengikuti akunnya.
- Log lewat `ActivityLogger`: `member.created|updated|role_changed|deactivated|reactivated|left|joined`.
- **Belum diketahui:** apakah `is_billable` bisa diubah dari halaman Anggota (lihat bagian 12, E2).

**Kategori dan transaksi (D)** — *tes hijau; belum ditinjau sesi lain*

- **Kategori:** hanya Admin (`manageSettings`). Nama dipangkas, wajib, maks 100, unik per organisasi tanpa peka huruf besar/kecil. Kategori `is_system` tidak bisa diubah atau dihapus. Kategori yang dipakai transaksi (termasuk soft-deleted) tidak bisa dihapus. Organisasi lain = 404. Log: `category.created|renamed|deleted`.
- **Transaksi:** semua anggota aktif melihat (`viewTransactions`); Admin/Editor mencatat dan mengubah (`manageTransactions`); hanya Admin menghapus (`deleteTransactions`, soft delete). `created_by` tidak berganti saat diubah.
- **Validasi (`TransactionRequest`):** `type` masuk|keluar; `category_id` milik organisasi dan **bukan sistem**; `amount` bulat 1 s.d. 100.000.000.000; `tanggal` `Y-m-d` min 2000-01-01; `description` maks 255.
- **Terkunci:** transaksi ber-`invoice_id` (`isLocked()`) tidak bisa diubah atau dihapus; halaman ubah dialihkan dengan pesan.
- **Daftar:** filter `month` (`Y-m`), `type`, `q` (LIKE); ringkasan mengikuti filter; 15 per halaman. Log: `transaction.created|updated|deleted`.

**Iuran (E1)** — *tes hijau (16 tes); alur di browser belum dicoba*

- Rute (`org.member`): `invoices.index` (GET `/iuran`), `invoices.store` (POST `/iuran`), `invoices.pay_cash` (PATCH `/iuran/{invoice}/lunas-tunai`), `invoices.cancel` (PATCH `/iuran/{invoice}/batalkan`). `{invoice}` dicari lewat `Invoice::inOrganization()` (organisasi lain = 404).
- **Hak saat ini:** semua anggota aktif membuka halaman (`viewInvoices`). **Admin** melihat semua tagihan satu periode (bawaan: terbaru; filter `period`) dan mengelola (`manageInvoices` = Admin saja). Editor dan Anggota hanya melihat tagihan **miliknya**, semua bulan, tanpa tombol. *(Akan direvisi di E2.)*
- **Buat tagihan:** satu periode (`YYYY-MM`, 2000-an) dan jumlah untuk semua anggota **aktif dan `is_billable`**; yang sudah punya tagihan periode itu dilewati (tidak dobel, jumlah lama tidak berubah). Log `invoice.generated`.
- **Lunas tunai:** dari `belum` atau `menunggu`; `DB::transaction` + `lockForUpdate`; tagihan jadi `lunas`/`tunai`/`paid_at`; membuat transaksi `masuk` kategori Iuran dengan `invoice_id`. **Keterangan "Iuran {Bulan} {Tahun}" tanpa nama anggota (D2).** Dua kali = galat `invoice`. Log `invoice.paid_cash`.
- **Batalkan pelunasan:** hanya untuk yang lunas; transaksi otomatis **dihapus permanen** (`forceDelete`, karena `transactions.invoice_id` unik); tagihan kembali `belum`; pelunasan ulang bisa. Log `invoice.payment_cancelled`.
- `Invoice`: konstanta `STATUS_*`/`METHOD_TUNAI`, `scopeInOrganization`, `isPaid()`, `labelFor('2026-10')` = "Oktober 2026", `periodLabel()`.
- Seeder: keterangan transaksi iuran diubah menjadi "Iuran Oktober 2026" (tanpa nama). `proof_path` tagihan Budi menunjuk berkas fiktif yang tidak ada di disk.

## 6. Peran dan hak akses

**Matriks yang sudah diterapkan di `OrganizationPolicy`** (semua = keanggotaan aktif):

| Kemampuan | Admin | Editor | Anggota | Bukan anggota |
| --- | :-: | :-: | :-: | :-: |
| `viewDashboard` | ya | ya | ya (D2) | – |
| `viewTransactions` — terpasang | ya | ya | ya | – |
| `viewInvoices` — terpasang (Admin semua, lainnya hanya miliknya) | ya | ya | ya | – |
| `viewReports` | ya | ya | – | – |
| `viewArrears` | ya | – | – | – |
| `manageTransactions` — terpasang | ya | ya | – | – |
| `deleteTransactions` — terpasang | ya | – | – | – |
| `manageInvoices` — terpasang (**saat ini Admin saja; E2: Admin + Editor**) | ya | – *(E2: ya)* | – | – |
| `manageMembers` — terpasang | ya | – | – | – |
| `manageInvitations` — terpasang | ya | – | – | – |
| `manageSettings` — terpasang | ya | – | – | – |

`OrganizationPolicyTest` memeriksa seluruh matriks (11 kemampuan).

**Aturan peran (diterapkan di Langkah C):** pembuat organisasi tidak bisa diturunkan atau dikeluarkan; tak seorang pun mengubah perannya sendiri; kode undangan hanya memberi Anggota. **Minimal satu Admin** terjamin tidak langsung (Admin tidak bisa diturunkan atau keluar); **harus dirancang ulang** bila promosi Admin dibuat (D5).

**Rencana aturan Iuran (disepakati pemilik, belum diterapkan; E2):** **tak seorang pun boleh melunasi, memverifikasi, atau membatalkan pelunasan tagihan yang `member.user_id`-nya dirinya sendiri**; tagihan pengelola diverifikasi orang lain. Organisasi tanpa pengelola kedua membuat tagihan pengelola menggantung: halaman Iuran harus menampilkan pesan jelas. Kolusi dua pengelola tidak bisa dicegah kode; yang tersedia hanya jejak di riwayat aktivitas dan transaksi yang terlihat semua anggota.

**Owner/Dev** punya controller/rute terpisah (`/owner/*`) yang **tidak pernah** menyentuh `transactions`, `invoices`, atau lampiran, dengan tes yang memastikannya. Pemegang server/database tetap bisa membaca data lewat alat database; yang dijamin hanya tidak adanya fitur mengintip, kata sandi ter-hash, dan panel berisi metadata.

## 7. Data demo dan pengujian

**Seeder** (`php artisan migrate:fresh --seed`). Kata sandi semua akun: `KitaDemo2026!`; semua terverifikasi.

| Peran | Email | Ditagih |
| --- | --- | :-: |
| Owner/Dev | `owner@kaskita.demo` | – |
| Admin (Nadia Putri) | `admin@kaskita.demo` | tidak |
| Editor (Rafi Pratama) | `editor@kaskita.demo` | tidak |
| Anggota (Dimas Saputra) | `anggota@kaskita.demo` | ya |
| Kontak tanpa akun | `sinta@`, `budi@`, `citra@` `kaskita.demo` | ya |

Organisasi **BEM Fakultas Teknologi**: saldo awal 500.000, iuran 25.000, kode `BEM26KAS` (permanen), kategori Iuran/Donasi/Konsumsi/Perlengkapan/Transportasi, 6 transaksi (2 otomatis dari iuran), 4 tagihan 2026-10 (2 lunas, 1 menunggu, 1 belum). Saldo awal dashboard = Rp 455.000. Seeder tidak idempotent. **Peran akun demo bisa berubah bila pemilik mengubahnya di halaman Anggota** (Dimas pernah menjadi Editor karena itu, bukan bug); `migrate:fresh --seed` mengembalikannya.

**Tes** (`php artisan test`; **141 lulus, 899 assertion**): Auth Breeze (18; login/daftar mengalihkan ke `organizations.index`); Example (2); `OrganizationTest` (18); `MembershipLinkingTest` (7); `AccountDeletionTest` (7); `OrganizationPurgeTest` (2); `RoutingTest` (3); `ProfileTest` (5); `OrganizationPolicyTest` (4, matriks 11 kemampuan); `InvitationManagementTest` (15); `MemberManagementTest` (19); `CategoryManagementTest` (11); `TransactionManagementTest` (14); **`InvoiceManagementTest` (16, E1: halaman per peran, Admin semua vs Anggota miliknya, tombol per peran, buat tagihan hanya anggota aktif+ditagih, tidak dobel, validasi isian, hanya Admin membuat, lunas tunai + transaksi tanpa nama, lunas dua kali ditolak, menunggu bisa dilunasi tunai, batalkan menghapus transaksi dan bisa lunas lagi, batalkan yang belum lunas ditolak, Editor/Anggota tidak bisa melunasi/membatalkan, organisasi lain 404, pilih bulan)**.

Catatan pengujian:

- Tes memakai SQLite di memori; aplikasi memakai MariaDB. Kolasi dan FK belum diuji di MariaDB.
- **Browser:** pemilik sudah mencoba login, daftar, `/organisasi`, dashboard, dan melihat halaman Transaksi (tautan tampil). Peran Dimas sempat Editor sehingga tombol Tambah/Ubah muncul (data, bukan kode). **Belum dicoba/dilaporkan:** alur Transaksi lengkap, Kategori, Undangan (tombol Salin hanya jalan di `localhost`/`127.0.0.1`/HTTPS), Anggota, tombol Keluar, dan **alur Iuran E1**. Pemilik juga melihat halaman Transaksi melebar penuh dan tanpa gaya (lihat masalah 23).
- **Uji mutasi:** E1 satu kali (Gate `manageInvoices` di `payCash` dikomentari → tepat satu tes gagal: `editor dan anggota tidak bisa melunasi atau membatalkan`; sudah dipulihkan). Mutasi `findInvoice` dan filter `member_id` belum dicoba. Langkah D belum diuji mutasi. Opsional (aturan 10).
- Verifikasi middleware rute: `route:list -v` dan `RoutingTest`.

## 8. Keputusan pemilik

| # | Pertanyaan | Keputusan / keadaan |
| --- | --- | --- |
| D1 | Hak **Editor** | Awal: default prototipe (catat/ubah transaksi, baca laporan tanpa tunggakan). **Revisi disepakati 11 Nov, diterapkan di E2:** Editor juga mengelola Iuran (membuat tagihan, lunas tunai, batalkan, verifikasi bukti) **kecuali tagihannya sendiri**. Pengaturan, anggota, undangan, kategori, hapus transaksi tetap hanya Admin. |
| D2 | Anggota melihat dashboard? | **Boleh, hanya lihat** (dashboard dan daftar transaksi). Keterangan transaksi otomatis iuran **tanpa nama anggota**; tunggakan hanya terlihat Admin. Diterapkan di E1 dan seeder. |
| D3 | Admin/Editor ikut ditagih? | **Boleh (disepakati 11 Nov):** Admin/Editor yang ingin membayar diset `is_billable = true` dan membayar seperti anggota lain, dengan syarat tidak memverifikasi tagihannya sendiri. Satu akun, bukan dua. Bawaan pembuat organisasi tetap `is_billable=false`. Dosen pengawas = Anggota dengan `is_billable=false`. |
| D4 | Admin menambah kontak dengan email akun terverifikasi | Tidak ditautkan otomatis. Usulan: undangan yang harus diterima. |
| D5 | Serah-terima Admin, promosi ke Admin, hapus organisasi | Belum ada UI. Admin belum bisa keluar. Jaminan "minimal satu Admin" harus dirancang ulang. |
| D6 | Nama organisasi boleh kembar? | Boleh. |
| D7 | CSS prototipe vs Tailwind Breeze | Belum ditentukan. Aturan pemilik: tampilan mengikuti halaman asli KasKita. |
| D8 | Locale `id` dan timezone `Asia/Jakarta` | Sekarang `en` dan `UTC`. Belum diubah. |
| D9 | Aturan kode undangan | 1/7/30 hari; kode awal 7 hari; banyak kode aktif; ditutup, tidak dihapus. |
| D10 | Lampiran | **Revisi:** bukti bayar iuran dikerjakan di **E3**; nota transaksi manual (`transactions.attachment_path`) menyusul sesudahnya. |
| D11 | Aturan bukti bayar (disepakati 11 Nov, diterapkan di E3) | Anggota menekan "Saya sudah bayar" pada tagihannya, memilih transfer/tunai; **bukti opsional**. Selama `menunggu`: bukti boleh ditambah, diganti, dihapus, atau pengajuan ditarik (kembali `belum`). Verifikator menyetujui (`lunas` + transaksi otomatis) atau menolak dengan alasan (kembali `belum`, alasan tampil, boleh ajukan ulang). Setelah `lunas`, bukti terkunci (batalkan pelunasan dulu). Bukti di disk **privat**, jpg/png/pdf maks 2 MB, nama acak, unduh lewat rute yang dicek hak (pemilik tagihan atau verifikator), file lama dihapus saat diganti. Label jelas "tanpa bukti" untuk verifikator. |

Bila langkah berikutnya butuh keputusan yang belum ada, **ajukan satu pertanyaan** dan tawarkan default yang aman.

## 9. Masalah yang diketahui / belum dikerjakan

1. **Aturan "tidak menyentuh tagihan sendiri" dan hak Editor di Iuran belum ada** (E2). Sampai itu, `manageInvoices` hanya Admin, jadi Admin yang ditagih (`is_billable=true`) bisa melunasi tagihannya sendiri.
2. **Pengajuan bayar dan bukti belum ada** (E3). Anggota belum bisa mengajukan pembayaran; semua pelunasan lewat Admin.
3. **Belum diketahui apakah `is_billable` bisa diubah dari halaman Anggota** (`members/index`, `UpdateMemberRequest`). Bila belum, Admin tidak bisa ikut ditagih lewat UI (E2).
4. **`ActivityLog`:** pindahkan `InvitationController::log()` (privat lama) ke `ActivityLogger`; periksa apakah pembuatan organisasi tercatat; modul baru wajib memakai `ActivityLogger`.
5. **Non-anggota mendapat 403 untuk organisasi yang ada dan 404 untuk yang tidak ada** (ID bisa ditebak). Usulan: 404 untuk keduanya.
6. **`OwnerSeeder`** memakai kata sandi tetap dan dipanggil tanpa syarat: jangan `db:seed` di server sungguhan sebelum diberi syarat lingkungan.
7. **Konfigurasi bawaan:** `APP_NAME=Laravel`, `APP_LOCALE=en`, timezone `UTC` (waktu di Undangan tampil UTC), tidak ada `lang/`, halaman auth/profil Inggris, `welcome.blade.php` bawaan.
8. **Dokumen basi:** `README.md` masih menjelaskan prototipe; `AGENTS.md` bawaan installer (aturan 5).
9. **Integritas lintas-organisasi** tidak dipaksa DB; wajib divalidasi di FormRequest/controller (kategori transaksi sudah; tagihan lewat `inOrganization`).
10. **Gabung dua kali serentak** bisa menabrak indeks unik (500).
11. **Pesan hapus akun Admin tunggal** menyuruh tindakan yang belum ada fiturnya (D5). Admin belum bisa keluar.
12. **MariaDB belum diuji lewat tes** (kolasi, FK).
13. **Penautan email** mengandalkan verifikasi; `MAIL_MAILER=log` cukup untuk pengembangan saja.
14. **`OrganizationPolicy::hasRole`** satu query per pemeriksaan; dashboard memanggil ±6 `@can`. Pertimbangkan `currentMember` atau cache per request.
15. **`.env` pernah tertempel di percakapan Claude** (memuat `APP_KEY` dan `DB_PASSWORD`). Disarankan `php artisan key:generate` dan ganti kata sandi `kaskita_user`, lalu `optimize:clear`. Jangan tempel `.env`.
16. **`BEM26KAS` permanen** (seeder): hanya untuk demo.
17. **Kode Langkah C (Pemandu) dan D (sesi Claude ketiga) belum ditinjau sesi lain**; D dan E1 belum lewat uji mutasi penuh (opsional).
18. **Lampiran nota transaksi belum ada** (menyusul setelah E3; D10).
19. **Pencarian transaksi (`q`)** memperlakukan `%` dan `_` sebagai wildcard; perbaikan: `addcslashes($filters['q'], '%_\\')`. Dampak kecil.
20. **Form "Tambah transaksi" ikut terbuka** bila filter `type` tidak valid (kunci galat sama). Kosmetik.
21. **Belum dicoba di browser:** Undangan, Anggota, Keluar, Kategori, alur Transaksi, Iuran.
22. **Pelunasan tunai mengisi `tanggal` transaksi dengan hari ini** (`now()`), bukan tanggal pembayaran yang sebenarnya; tidak ada input tanggal. Zona waktu UTC (D8) bisa menggeser tanggal di sekitar tengah malam WIB.
23. **Tampilan halaman Transaksi tampak melebar penuh dan tanpa kontainer** pada tangkapan layar pemilik. Dugaan: `npm run dev` tidak berjalan atau CSS belum ter-build. **Belum dikonfirmasi.**
24. **Organisasi tanpa pengelola kedua:** tagihan Admin yang ditagih akan menggantung (E2 menambah pesan).

## 10. Aturan kerja (ringkas)

- Baca bagian 0, 5, 6, 8 sebelum mengubah apa pun; bandingkan dengan kode.
- Tambahkan **tes untuk setiap aturan baru**; tes lama tetap lulus. Tes harus benar-benar gagal bila aturannya dilanggar. Uji mutasi opsional (aturan 10 dan 11, bagian 0).
- Jangan mengubah migrasi yang ada tanpa memberi tahu pemilik; untuk perubahan skema berikutnya pertimbangkan migrasi baru.
- Validasi di FormRequest, otorisasi di Policy, logika multi-tabel di `DB::transaction`.
- Sumber daya organisasi lewat `organisasi/{organization}/...` dengan `scopeBindings`; tulis tes bahwa ID organisasi lain = 404.
- Lampiran di disk **privat** (`FILESYSTEM_DISK=local`), divalidasi tipe dan ukuran di server, diunduh lewat rute yang dicek Policy; jangan simpan sebagai data URL.
- Teks UI Bahasa Indonesia, ramah dan jelas; pesan galat memberi langkah berikutnya. Jangan tampilkan data keuangan di panel Owner/Dev.
- Jangan menyimpan rahasia di repo, seeder, atau percakapan.
- Tampilan mengikuti halaman asli KasKita.
- Untuk berkas yang diganti seluruhnya, beri **isi file lengkap**; jangan beri potongan yang bisa bentrok.

## 11. Riwayat progres (ringkas)

| Tanggal | Perubahan | Tes |
| --- | --- | --- |
| sebelum 3 Nov 2026 | Prototipe statis; Tahap 1–2 (11 tabel, 9 model); seeder demo; Breeze; `OrganizationController`, `EnsureOrganizationMember`, dashboard organisasi. | 1 dari 25 |
| 3 Nov | Audit fondasi: `MustVerifyEmail` + penautan setelah verifikasi, email kontak huruf kecil, hapus akun menonaktifkan keanggotaan, `Organization::purge()`, `/organisasi` lengkap, rute ganda dibersihkan, `platform_role` tidak mass-assignable. | 62 |
| 5 Nov | **Langkah A:** `OrganizationPolicy`, `hasRole()`, gerbang dashboard. D1 (default), D2. | 66 |
| 6 Nov | **Langkah B:** kelola undangan (`InvitationController`, request, view, 15 tes); lima uji mutasi. | 81 |
| 7 Nov | **Langkah C:** kelola anggota (Pemandu; belum ditinjau): `ActivityLogger`, `MemberController`, request, view, 19 tes; tombol Keluar. | 100 |
| 11 Nov | **Langkah D** (sesi Claude ketiga; kode dibaca, tidak dijalankan): Category/Transaction controller + request + view + 25 tes; Policy `viewTransactions`; dashboard dapat tautan; `dashboard.blade.php` bawaan dihapus; `amount` terkonfirmasi `bigint`. | 125 (797) |
| 11 Nov | **Langkah E1:** `InvoiceController`, `StoreInvoiceRequest`, `invoices/index`, `InvoiceManagementTest` (16); `Invoice` (konstanta, `inOrganization`, `labelFor`); Policy `viewInvoices`; `routes/web.php` (+4 rute, `invitations.regenerate` dipindah ke blok undangan); dashboard (+tautan Iuran); seeder (keterangan tanpa nama). Pint merapikan 6 file. Satu uji mutasi berhasil. Dikonfirmasi pemilik: **141 lulus (899)**. **Belum diverifikasi:** alur Iuran di browser. Koreksi: Claude sempat menulis 140; yang benar 141 (tes Iuran 16, bukan 15). Kesepakatan desain (D1 revisi, D3, D10 revisi, D11) tercatat di bagian 8. | **141 (899)** |

Perintah pemilik setelah menyalin serahan: salin file → `composer update --lock` (bila `composer.json` berubah) → `php artisan migrate:fresh --seed` (bila migrasi atau seeder berubah) → `php artisan optimize:clear` → `php artisan test`. Pemilik melaporkan baris `Tests: N passed (M assertions)`; catat angka sebenarnya di bagian 11.

## 12. Langkah berikutnya (urut; satu langkah per percakapan)

**Langkah A–D: SELESAI** (D kecuali lampiran nota; lihat D10 dan masalah 18). **Langkah E1: SELESAI** (tes hijau; browser belum).

**Langkah E2 — Hak Iuran (Editor dan "tidak menyentuh tagihan sendiri")** ← *berikutnya*
- **Sebelum menulis kode, minta pemilik menempel:** `app/Http/Controllers/InvoiceController.php` (versi setelah Pint), `app/Http/Controllers/MemberController.php`, `app/Http/Requests/UpdateMemberRequest.php`, `StoreMemberRequest.php`, `resources/views/members/index.blade.php`, `resources/views/invoices/index.blade.php` bila pemilik mengubahnya.
- **Policy:** `manageInvoices` menjadi Admin + Editor. Perbarui `OrganizationPolicyTest` (matriks: Editor `manageInvoices` = true) dan tes Iuran yang mengasumsikan Editor ditolak (`tombol mengikuti peran`, `hanya admin boleh membuat tagihan`, `editor dan anggota tidak bisa melunasi atau membatalkan`; Anggota dan non-anggota tetap ditolak).
- **Aturan di `InvoiceController` (`payCash`, `cancel`):** tolak (galat `invoice` dengan pesan jelas; default, bisa diganti 403) bila `member.user_id` = pengguna yang bertindak. Di view: sembunyikan tombol pada tagihan sendiri dan tampilkan keterangan "Menunggu diverifikasi pengelola lain". Pesan khusus bila organisasi tidak punya pengelola lain.
- **`is_billable` dari halaman Anggota** (Admin mengubah apakah seseorang ditagih), bila belum ada; tercatat di `ActivityLogger`.
- **Tes:** Editor membuat tagihan, melunasi tagihan orang lain, membatalkan tagihan orang lain; Admin dan Editor **tidak bisa** melunasi/membatalkan tagihannya sendiri; skenario dua orang (Admin dan Editor saling memverifikasi); pesan untuk organisasi tanpa pengelola lain; Anggota tetap tanpa tombol; mutasi opsional: hapus pemeriksaan "tagihan sendiri" → tepat satu tes gagal.
- Perbarui bagian 6 (matriks), 8 (D1 diterapkan), 9.

**Langkah E3 — Pengajuan pembayaran dan bukti**
- **Sebelum menulis kode, minta pemilik menempel:** `config/filesystems.php` (disk privat), view `invoices/index`, `InvoiceController` terbaru.
- Sesuai D11: aksi Anggota (pemilik tagihan) **"Saya sudah bayar"** (transfer/tunai, bukti opsional) → `menunggu`; tambah/ganti/hapus bukti dan tarik pengajuan selama `menunggu`; verifikator **setujui** (`lunas` + transaksi otomatis, memakai logika `payCash`) atau **tolak** dengan alasan (kembali `belum`, `rejection_reason` tampil); bukti terkunci setelah lunas. Unduh bukti lewat rute yang dicek hak. File lama dihapus saat diganti. `Storage::fake` di tes. Aturan "tidak memverifikasi tagihan sendiri" berlaku juga untuk setujui/tolak.
- Seeder: berkas bukti fiktif Budi → tampilan "berkas tidak tersedia" (jangan error).
- Tes: pengajuan tanpa/dengan bukti, ganti bukti menghapus file lama, tolak jenis/ukuran tidak valid, hanya pemilik mengajukan (Anggota lain 403/404), organisasi lain 404, unduh hanya pemilik atau verifikator, bukti terkunci setelah lunas, tolak lalu ajukan ulang.

**Langkah F — Laporan dan tutup buku**
Gerbang `viewReports`/`viewArrears`. Filter bulan dan minggu (minggu dipotong di batas bulan), saldo awal periode = `opening_balance` + transaksi sebelum periode, CSV dan PDF, `PeriodClosure` (bulan ditutup tidak bisa diedit; menyentuh `TransactionController` dan Iuran).

**Langkah G — Panel Owner/Dev**
`/owner/*`: daftar organisasi (nama, tanggal dibuat, jumlah anggota, status), aktif/nonaktif; tes bahwa tidak menyentuh data keuangan.

**Langkah H — Tampilan (Tahap 5) dan penyempurnaan**
View prototipe halaman demi halaman (putuskan D7), navigasi/sidebar organisasi, terjemahkan teks Breeze, atur D8, perbarui `README.md`, hapus `welcome.blade.php`, syarat lingkungan untuk `OwnerSeeder`, uji di MariaDB, lampiran nota transaksi (D10), pertimbangkan Pull Request ke `main` (perhatikan GitHub Pages).

**Peta halaman prototipe → Laravel:** `organisasi.html`→`organizations.index` (ada) · `dashboard.html`→`organizations.dashboard` (dasar) · `transaksi.html`→`TransactionController` (ada) · `iuran.html`→`InvoiceController` (E1 ada; E2–E3 menyusul) · `anggota.html`→`MemberController` + `InvitationController` (ada) · `laporan.html`→`ReportController` · `pengaturan.html`→pengaturan organisasi + `CategoryController` (kategori ada) · `owner.html`→`Owner\OrganizationController`.