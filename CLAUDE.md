# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude. **Perbarui di setiap kemajuan** (bagian 2, 9, 11, 12 wajib).
> Terakhir diperbarui: **6 Nov 2026** · Tes: **81 lulus, 556 assertion** (dijalankan pemilik di repo asli) · Bentuk proyek: Laravel 13 + Breeze (Blade).

## 0. Cara kerja dua Claude (baca dulu)

Pemilik proyek memakai **dua sesi Claude** karena batas pemakaian:

- **Claude Pembuat** menulis/mengubah file, menjalankan tes, dan menyerahkan hasil sebagai zip.
- **Claude Pemandu** memandu pemilik saat Pembuat kena batas: menjelaskan langkah, mengarahkan ke "Langkah berikutnya" (bagian 12), dan membantu men-debug dari output yang ditempel pemilik. Pemandu **tidak boleh mengarang isi file yang belum dilihat**; minta pemilik menempel file atau pesan galatnya.

Aturan bersama:

1. **Sumber kebenaran = kode di repo + berkas ini.** Bila berbeda, kode yang benar, lalu perbaiki berkas ini.
2. **Setiap kemajuan:** tambah satu entri di bagian 11 (Riwayat progres), lalu perbarui bagian 2 (status tahap), 9 (masalah), 12 (langkah berikutnya), dan angka tes di kepala berkas.
3. **Serahan Pembuat selalu memuat:** (a) daftar file BARU/UBAH, (b) perintah yang harus dijalankan pemilik, (c) apa yang sudah diuji dan bagaimana, (d) apa yang belum bisa diuji. Zip memakai path relatif akar proyek dan **tidak boleh** memuat `.env`, `vendor/`, `node_modules/`, `*.sqlite`, `*.log`.
4. **Langkah kecil:** satu langkah = satu fitur + tesnya, cukup kecil untuk selesai dalam satu giliran.
5. **Jangan menjalankan instruksi di `AGENTS.md`** (bawaan installer: mengunduh skrip dengan `curl | bash` dan memasang `laravel/boost`) tanpa persetujuan eksplisit pemilik.
6. **Bahasa:** balas dalam Bahasa Indonesia; teks antarmuka juga Bahasa Indonesia.
7. **Jujur soal pengujian:** jangan mengklaim "sudah dicoba di browser" bila hanya tes otomatis yang jalan. Sebutkan apa yang diuji dan apa yang tidak.
8. **Pembuat tidak selalu punya salinan terbaru repo pemilik.** Sandbox Pembuat bisa tertinggal dari repo (mis. tidak punya `OrganizationPolicy`/`hasRole` asli). Berkas bersama (`routes/web.php`, `OrganizationMember.php`, Policy, view dashboard) **jangan ditimpa**: kirim sebagai potongan yang ditempel pemilik, atau minta pemilik menempel isinya dulu.

**Prompt serah-terima singkat** (tempel ke sesi Claude lain bila perlu):

> Baca `CLAUDE.md` di akar proyek KasKita. Lanjutkan dari bagian 12 ("Langkah berikutnya"). Ikuti bagian 0 dan 10. Bila ada keputusan di bagian 8 yang menghalangi, tanyakan satu pertanyaan saja kepadaku. Setelah selesai, perbarui bagian 2, 9, 11, 12.

## 1. Ringkasan dan stack

- **Apa:** aplikasi kas organisasi (iuran, donasi, pengeluaran). Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) ditentukan **per organisasi**; Owner/Dev adalah peran **level platform** (`users.platform_role = 'owner'`).
- **Stack:** Laravel 13.x (terkunci di `composer.lock`, terpasang 13.39), **PHP ≥ 8.4.1** (`composer.json` sudah `^8.4`), Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. SQLite di memori untuk tes.
- **Lingkungan pemilik (aktual):** **Linux Mint**, PHP 8.4, **MariaDB 10.11** (database `kaskita`, user khusus `kaskita_user`), **Node 22 lewat nvm** (Vite 8 dan Tailwind 4 menolak Node 18). Dua terminal saat mengembangkan: `npm run dev` dan `php artisan serve` (`http://127.0.0.1:8000`). **Bukan Laragon** (Laragon hanya untuk Windows; sebutan itu di catatan lama sudah usang). phpMyAdmin tidak ada; periksa database dengan `php artisan db:show`, `php artisan db:table <tabel>`, atau Tinker.
- **Asal desain:** prototipe statis (HTML/CSS/JS, commit `4a99c24`) sudah menentukan alur dan tampilan. Laravel menerjemahkannya ke server. **View prototipe belum dimasukkan** ke proyek ini.
- Pemilik membaca dan mengikuti serahan Claude langkah demi langkah; jelaskan perintah dengan jelas.

### Repo dan Git

- Repo: `https://github.com/AzkalGit/KasKita`. Folder lokal: `~/Documents/Kuliah/sem3/SI/KasKita` (Laravel di **akar repo**).
- Branch `main` = **prototipe statis, sengaja belum disentuh** (GitHub Pages `azkalgit.github.io/KasKita/` menyajikannya; mengganti `main` dengan Laravel akan merusak situs itu). Seluruh pekerjaan Laravel ada di branch **`fitur/laravel-fondasi`**. Gabung ke `main` lewat Pull Request nanti, setelah dipertimbangkan soal GitHub Pages.
- Prototipe lama dipindah ke `prototipe-statis/` (termasuk `KASKITA-STATUS.txt`); catatan tahap ada di `docs/TAHAP.txt`.
- Commit penting: `89b8cd9` (migration, model, seeder, struktur), `f134c83` (Breeze). Perbaikan zip 3 Nov dan Langkah A dikomit oleh pemilik di branch yang sama.

### Perintah penting

```bash
composer install
cp .env.example .env && php artisan key:generate     # lalu isi DB_* (MariaDB/MySQL, pakai user khusus, bukan root)
php artisan migrate:fresh --seed                     # MENGHAPUS isi DB lokal; migrasi lama diedit di tempat
npm install && npm run dev                           # Node 22; atau npm run build
php artisan test                                     # SQLite di memori, tidak menyentuh MariaDB/MySQL
vendor/bin/pint <file yang diubah>                   # gaya kode
php artisan route:list -v                            # lihat middleware tiap rute (tanpa -v middleware tidak tampil)
php artisan optimize:clear                           # bila rute/view/config tampak tidak berubah
```

Bila `composer.json` diubah, jalankan `composer update --lock` agar hash `composer.lock` sesuai.

## 2. Status per tahap (mengikuti `docs/TAHAP.txt`)

| Tahap | Status | Catatan |
| --- | --- | --- |
| 1. Analisis dan desain database | **Selesai** | Skema ada di migrasi (bagian 4). ERD ditulis sebagai DBML di percakapan (dbdiagram.io); berkas ERD tidak ada di repo. Keputusan: diagram DB biasa (crow's foot), bukan Chen. |
| 2. Migration dan Model | **Selesai** | 9 model + relasi. Empat migrasi diedit di tempat (kolom pencatat jadi `nullOnDelete`). |
| 3. Autentikasi dan sistem akses | **Sebagian** | Ada: login/daftar/reset (Breeze), email wajib terverifikasi, middleware `org.member`, organisasi aktif lewat URL, `OrganizationPolicy` (matriks hak per peran, Langkah A). Gerbang terpasang di **dashboard dan undangan**; gerbang lain dipasang bersama tiap modul. **Belum:** Policy untuk sumber daya lain (transaksi, tagihan per baris), panel Owner. |
| 4. Controller dan logika bisnis | **Sebagian** | Ada: `OrganizationController` (index/store/join), `OrganizationDashboardController` (show), **`InvitationController` (kelola kode, Langkah B)**, hapus akun di `ProfileController`. **Belum:** Transaction, Invoice, Member, Category, Report, Owner. `ActivityLog` baru ditulis untuk aksi undangan. |
| 5. View (Blade) | **Sebagian** | `organizations/index` (fungsional), `organizations/dashboard` (sementara), **`invitations/index` (fungsional, gaya Breeze)**. Halaman auth/profil masih bahasa Inggris bawaan Breeze. |
| 6. Pengujian dan penyempurnaan | **Sebagian** | 81 tes lulus. Belum menguji iuran, transaksi, laporan (modulnya belum ada). |

## 3. Struktur dan konvensi

```
app/Http/Controllers/   OrganizationController, OrganizationDashboardController, InvitationController,
                        ProfileController, Auth/* (Breeze)
app/Http/Requests/      StoreInvitationRequest    (buat/ganti kode; authorize = manageInvitations)
app/Http/Middleware/    EnsureOrganizationMember  (alias: org.member, didaftarkan di bootstrap/app.php)
app/Listeners/          LinkPendingMemberships    (event Verified; terdeteksi otomatis)
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice,
                        Invitation, InvitationUse, ActivityLog, PeriodClosure
app/Policies/           OrganizationPolicy        (terdeteksi otomatis untuk model Organization)
database/migrations/    2026_10_31_* (tabel KasKita) + bawaan Laravel
database/seeders/       OwnerSeeder, DemoOrganizationSeeder (dipanggil DatabaseSeeder)
resources/views/        organizations/{index,dashboard}.blade.php ; invitations/index.blade.php ;
                        dashboard.blade.php (TIDAK terpakai, boleh dihapus) ; welcome.blade.php (bawaan)
routes/web.php          rute aplikasi ;  routes/auth.php  HANYA rute Breeze (jangan taruh rute aplikasi di sini)
tests/Feature/          lihat bagian 7
prototipe-statis/       prototipe HTML/CSS/JS lama (referensi tampilan)
docs/                   TAHAP.txt
```

Konvensi:

- **Nama tabel/kolom/rute/nama rute Bahasa Inggris** (`organizations.index`, `invoices`, `member_id`); **URI dan teks UI Bahasa Indonesia** (`/organisasi`, `/undangan`, "Gabung dengan kode"). Kolom `tanggal` dan `period` adalah pengecualian yang sudah ada.
- **Satu controller per modul**; logika yang menyentuh banyak tabel dibungkus `DB::transaction`, idealnya di metode model/kelas aksi (contoh: `Organization::purge()`, `Invitation::issue()`).
- **Uang** = bilangan bulat rupiah. **Tanggal transaksi** = kolom `date`; **periode iuran** = `char(7)` `'YYYY-MM'`.
- **Email** disimpan huruf kecil (`OrganizationMember::email` mutator). Email kosong disimpan `NULL`.
- **Organisasi aktif ditentukan oleh URL**, bukan session; setiap rute dalam organisasi berada di grup `org.member` dengan model binding `{organization}`. Grup itu memakai **`->scopeBindings()`**: parameter anak (mis. `{invitation}`) hanya dicari di dalam organisasi pada URL, sehingga ID milik organisasi lain menjadi 404. Pakai pola yang sama untuk sumber daya anak berikutnya (tagihan, transaksi, anggota).
- **Otorisasi:** `Gate::authorize('<kemampuan>', $organization)` di controller dan `@can('<kemampuan>', $organization)` di view; di view bisa memakai `$currentOrganization` (dibagikan middleware). Untuk aksi yang menerima input, taruh pemeriksaan di **`FormRequest::authorize()`** (dijalankan sebelum validasi) supaya peran yang tidak berhak langsung 403, bukan melihat galat validasi. Nama kemampuan ada di `OrganizationPolicy` (bagian 6). Peran dicek lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_ADMIN/ROLE_EDITOR/ROLE_ANGGOTA`.
- **Jangan menaruh rute aplikasi di `routes/auth.php`.** Rute yang tertulis dua kali diam-diam menimpa yang pertama; `RoutingTest` menjaga ini.
- Jalankan `vendor/bin/pint` pada file yang diubah.

## 4. Skema database (aktual, dari migrasi)

Tanda `?` = boleh NULL. `FK(hapus)` = aturan saat induk dihapus.

| Tabel | Kolom | FK(hapus) | Unik |
| --- | --- | --- | --- |
| `users` | name, email, email_verified_at?, password (cast `hashed`), platform_role? (`'owner'`), remember_token | – | email |
| `organizations` | name, status=`aktif`\|`nonaktif`, opening_balance=0, default_dues=0, payment_info?, created_by? | created_by→users (**set null**) | – |
| `organization_members` | organization_id, user_id?, name, email?, phone?, role? (`admin`\|`editor`\|`anggota`), is_billable=1, is_active=1 | organization_id→organizations (cascade); user_id→users (**set null**) | (organization_id, email); (organization_id, user_id) |
| `categories` | organization_id, name, is_system=0 (true untuk "Iuran") | organization_id→organizations (cascade) | (organization_id, name) |
| `invoices` | member_id, period, amount, status=`belum`\|`menunggu`\|`lunas`, method? (`transfer`\|`tunai`), proof_path?, rejection_reason?, paid_at? | member_id→organization_members (**restrict**) | (member_id, period) |
| `transactions` | organization_id, category_id, invoice_id?, created_by?, type (`masuk`\|`keluar`), amount, tanggal, description, attachment_path?, deleted_at? (soft delete) | organization_id (cascade); category_id (**restrict**); invoice_id→invoices (**restrict**); created_by→users (**set null**) | invoice_id |
| `invitations` | organization_id, code, expires_at?, closed_at?, created_by? (hanya `created_at`, tanpa `updated_at`) | organization_id (cascade); created_by (**set null**) | code |
| `invitation_uses` | invitation_id, user_id, used_at (tanpa timestamps) | invitation_id (cascade); user_id (cascade) | – |
| `activity_logs` | organization_id?, user_id?, action, subject_type?, subject_id?, metadata? (JSON; hanya `created_at`) | organization_id (set null); user_id (set null) | – |
| `period_closures` | organization_id, period, closed_by?, closed_at (tanpa timestamps) | organization_id (cascade); closed_by (**set null**) | (organization_id, period) |

Catatan skema:

- **Dua jenis "anggota" dalam satu tabel:** `user_id` terisi = anggota punya akun; `user_id` NULL = kontak yang ditagih (belum punya akun). Peran hanya bermakna bila akun ada.
- **`is_billable` ≠ `is_active`:** `is_billable` = apakah ditagih iuran; `is_active` = apakah masih tercatat aktif (nonaktif, bukan dihapus, bila punya riwayat tagihan).
- **`invoices` tidak punya `organization_id`**; organisasi dicapai lewat `member`. `transactions.category_id` tidak dipaksa DB untuk satu organisasi dengan transaksinya, jadi **validasi di FormRequest/controller** bahwa kategori milik organisasi yang sama.
- Kolom `role`, `status`, `type`, `method` adalah string tanpa CHECK constraint; validasi ada di aplikasi.
- `restrictOnDelete` pada `invoices.member_id` dan `transactions.invoice_id` **disengaja**. Karena itu `$organization->delete()` langsung gagal bila sudah ada tagihan; pakai `Organization::purge()`.
- Owner **tidak** punya tabel sendiri: cukup `users.platform_role = 'owner'`, dan tidak punya baris di `organization_members`.
- **Migrasi lama diedit di tempat.** Database yang dibuat sebelum 3 Nov 2026 perlu `migrate:fresh`. Langkah B **tidak** menambah migrasi.

## 5. Aturan bisnis yang SUDAH ada di kode

**Registrasi dan verifikasi email**

- `User implements MustVerifyEmail`. Semua rute organisasi di grup `['auth','verified']`; profil hanya `auth`.
- `/dashboard` hanya pengalihan ke `/organisasi` (nama rute `dashboard` dipertahankan karena Breeze memakainya). Login dan daftar juga mendarat di `/organisasi`.
- Mendaftar **tidak** menautkan akun ke kontak. Penautan terjadi **setelah email terverifikasi** (`LinkPendingMemberships` → `User::linkPendingMemberships()`): hanya baris `user_id` NULL, `is_active` = true, email sama, di organisasi yang belum diikuti akun itu; `role` NULL menjadi `anggota`. (Jangan menautkan di `RegisteredUserController`: email belum terbukti milik pendaftar.)
- Akun dihapus (`User::deleting`): keanggotaannya dinonaktifkan (`role` NULL, `is_active` false). Baris nonaktif tidak dihidupkan lagi oleh penautan maupun kode undangan.
- Admin **tunggal** sebuah organisasi tidak bisa menghapus akunnya (`User::organizationsWhereSoleAdmin()`).
- `platform_role` **tidak** mass-assignable; isi lewat `forceCreate`/`forceFill` (seeder). Form daftar hanya menerima name, email, password, jadi tidak ada yang bisa mendaftar sebagai Owner.
- Verifikasi email: dengan `MAIL_MAILER=log`, **tautan verifikasi dibaca dari `storage/logs/laravel.log`**. Akun demo sudah terverifikasi.

**Organisasi dan undangan**

- Membuat organisasi: pembuat jadi `admin`, `is_billable=false`, kategori "Iuran" (`is_system`) dibuat, dan satu kode undangan dibuat lewat `Invitation::issue($org, Invitation::DEFAULT_DAYS, $user)`: **berlaku 7 hari** (bukan permanen lagi).
- Gabung dengan kode: peran selalu `anggota`; kode dinormalkan (`trim`, huruf besar). **Batas percobaan:** 5 kegagalan per akun dalam 15 menit (`RateLimiter`, kunci `join-code:{user_id}`), dihapus saat berhasil. Pesan galat sama untuk kode salah, ditutup, kedaluwarsa, atau organisasi nonaktif. Ditolak bila sudah anggota, atau keanggotaannya nonaktif. Tercatat di `invitation_uses`.
- `EnsureOrganizationMember`: harus anggota **aktif** (403) dan organisasi `aktif` (403). Menaruh `member` di request attributes serta `currentOrganization` dan `currentMember` di view.
- **`OrganizationPolicy` (Langkah A):** satu metode per kemampuan, semuanya hanya berlaku untuk keanggotaan **aktif** (lihat matriks di bagian 6). Dashboard: saldo = `opening_balance` + pemasukan − pengeluaran (dihitung, tidak disimpan); lima transaksi terbaru.
- `Organization::purge()`: hapus transaksi (termasuk soft-deleted) → tagihan → organisasi dalam satu transaksi DB. Belum ada rute/UI yang memanggilnya.

**Kelola undangan (Langkah B)**

- Rute (di grup `org.member`, `scopeBindings`): `GET /organisasi/{organization}/undangan` (`invitations.index`), `POST /undangan` (`store`), `PATCH /undangan/{invitation}/tutup` (`close`), `PATCH /undangan/{invitation}/buka` (`reopen`), `POST /undangan/{invitation}/ganti` (`regenerate`).
- **Hanya Admin** (`manageInvitations`): `index/close/reopen` memakai `Gate::authorize`; `store/regenerate` memakai `StoreInvitationRequest::authorize()`. Editor, Anggota, dan non-anggota mendapat 403.
- Masa berlaku dipilih dari `Invitation::DURATIONS = [1, 7, 30]` hari (`days`, wajib). Satu organisasi boleh punya **banyak kode aktif sekaligus**.
- `Invitation::status()`: `ditutup` (menang) → `kedaluwarsa` → `aktif`. Kode yang sudah kedaluwarsa **tidak bisa dibuka lagi** (galat pada key `invitation`) dan tidak perlu ditutup. Menutup kode yang sudah tertutup atau membuka kode yang sudah aktif tidak mengubah apa pun dan tidak dicatat.
- **Ganti kode** (`regenerate`): menutup kode lama (bila masih bisa dipakai) dan membuat kode baru dalam satu `DB::transaction`. Kode **tidak pernah dihapus**, hanya ditutup (riwayat `invitation_uses` tetap utuh).
- Kode dibuat oleh `Invitation::generateCode()`: 8 karakter dari alfabet tanpa `0/O/1/I` (`Invitation::ALPHABET`), dipastikan belum dipakai kode lain di organisasi mana pun. Kode lama (mis. seeder `BEM26KAS`, permanen) tetap berlaku untuk gabung, termasuk bila memuat `0/O/1/I`, karena gabung hanya menormalkan huruf besar.
- **Riwayat aktivitas:** setiap aksi tercatat di `activity_logs` (`organization_id`, `user_id`, `subject_type=Invitation`, `subject_id`) dengan `action` `invitation.created | invitation.closed | invitation.reopened | invitation.regenerated` dan `metadata` `{code, days?, replaced_code?}`. Pencatatnya saat ini metode privat `InvitationController::log()`; jadikan satu helper bersama saat Langkah C.
- Halaman `invitations/index`: buat kode (pilih masa berlaku), tabel kode (status, berlaku sampai atau "Tanpa batas waktu", jumlah pemakaian, tombol Salin lewat Alpine, Tutup/Buka, Ganti kode), dan daftar "Bergabung lewat kode" (nama, email, kode, waktu). Waktu ditampilkan apa adanya (UTC; lihat D8).

## 6. Peran dan hak akses

**Matriks yang sudah diterapkan di `OrganizationPolicy`** (semua = keanggotaan aktif):

| Kemampuan (nama di Policy) | Admin | Editor | Anggota | Bukan anggota |
| --- | :-: | :-: | :-: | :-: |
| `viewDashboard` (saldo, pemasukan, pengeluaran, transaksi terbaru) | ya | ya | **ya (D2)** | – |
| `viewReports` | ya | ya | – | – |
| `viewArrears` (tunggakan di laporan) | ya | – | – | – |
| `manageTransactions` (catat/ubah) | ya | ya | – | – |
| `deleteTransactions` | ya | – | – | – |
| `manageInvoices` (kelola tagihan, verifikasi, lunas tunai) | ya | – | – | – |
| `manageMembers` (anggota, ubah peran Anggota↔Editor) | ya | – | – | – |
| `manageInvitations` (kode undangan) — **terpasang di `InvitationController`** | ya | – | – | – |
| `manageSettings` (saldo awal, iuran, info bayar, kategori) | ya | – | – | – |

Hak Anggota "lihat dan bayar tagihan **sendiri**" belum di Policy: itu aturan per baris tagihan, dibuat di Langkah E bersama `InvoicePolicy`.

**Aturan peran yang disepakati (belum diterapkan, dikerjakan di Langkah C):** pembuat organisasi tidak bisa diturunkan atau dikeluarkan; organisasi harus selalu punya minimal satu Admin; tak seorang pun mengubah perannya sendiri; kode undangan hanya memberi peran Anggota (**sudah** berlaku).

**Owner/Dev** harus punya controller/rute terpisah (`/owner/*`) yang **tidak pernah** menyentuh `transactions`, `invoices`, atau lampiran, dengan tes yang memastikannya. Perlu diakui terus terang: pemegang server/database tetap bisa membaca data lewat alat database; yang dijamin hanya tidak adanya fitur mengintip, kata sandi ter-hash, dan panel berisi metadata saja.

## 7. Data demo dan pengujian

**Seeder** (`php artisan migrate:fresh --seed`). Kata sandi semua akun: `KitaDemo2026!`; semua sudah terverifikasi.

| Peran | Email | Ditagih |
| --- | --- | :-: |
| Owner/Dev | `owner@kaskita.demo` | – |
| Admin (Nadia Putri) | `admin@kaskita.demo` | tidak |
| Editor (Rafi Pratama) | `editor@kaskita.demo` | tidak |
| Anggota (Dimas Saputra) | `anggota@kaskita.demo` | ya |
| Kontak tanpa akun | `sinta@`, `budi@`, `citra@` `kaskita.demo` | ya |

Organisasi **BEM Fakultas Teknologi**: saldo awal 500.000, iuran standar 25.000, kode undangan `BEM26KAS` (**permanen**, sengaja untuk demo), kategori Iuran/Donasi/Konsumsi/Perlengkapan/Transportasi, 6 transaksi (3 masuk = 250.000 dengan 2 otomatis dari iuran; 3 keluar = 295.000), 4 tagihan periode 2026-10 (2 lunas, 1 menunggu, 1 belum). **Saldo dashboard = Rp 455.000.** Seeder **tidak idempotent** (pakai `migrate:fresh --seed`). Akun Owner belum punya halaman apa pun (panel Owner = Langkah G).

**Tes** (`php artisan test`; **81 lulus, 556 assertion**): Auth bawaan Breeze (disesuaikan: login dan daftar mengalihkan ke `organizations.index`); `OrganizationTest` (18); `MembershipLinkingTest` (7); `AccountDeletionTest` (7); `OrganizationPurgeTest` (2); `RoutingTest` (3); `ProfileTest`; `OrganizationPolicyTest` (4); **`InvitationManagementTest` (15, Langkah B: tampilan dan status, hanya Admin untuk 5 aksi × 3 peran, verifikasi email, isolasi lintas organisasi, buat/validasi masa berlaku, tutup/buka, kedaluwarsa, ganti kode, pencatatan aktivitas, alfabet kode)**.

Catatan pengujian:

- Tes memakai **SQLite di memori**, sedangkan aplikasi nyata memakai **MariaDB**. Perilaku yang bisa beda (kolasi huruf besar/kecil, perubahan FK) belum diuji di MariaDB.
- Tampilan **belum pernah dilihat Claude di browser**; pemilik sudah mencoba login, daftar, `/organisasi`, dan dashboard. Halaman `invitations/index` belum dicoba di browser oleh siapa pun; **tombol Salin memakai Alpine + `navigator.clipboard`**, yang hanya jalan di konteks aman (`localhost` / `127.0.0.1` atau HTTPS).
- **Uji mutasi** dipakai untuk membuktikan tes bukan basa-basi: rusak aturannya sementara (hapus `Gate::authorize`, `authorize()` selalu true, hapus `scopeBindings`, izinkan membuka kode kedaluwarsa, kode awal permanen) dan pastikan tepat satu tes gagal; lalu pulihkan. Ulangi untuk aturan penting berikutnya.
- Untuk memverifikasi middleware sebuah rute, pakai `route:list -v` dan `RoutingTest`, bukan `Route::getRoutes()` (duplikat tertimpa).

## 8. Keputusan pemilik

| # | Pertanyaan | Keputusan / keadaan |
| --- | --- | --- |
| D1 | Hak **Editor** | **Default prototipe dipakai (sempit):** catat/ubah transaksi dan baca laporan tanpa tunggakan; tanpa Iuran/Anggota/Pengaturan/Undangan. Mudah dilonggarkan di Policy. |
| D2 | Anggota melihat dashboard? | **Diputuskan: boleh, hanya lihat.** Anggota melihat saldo, total pemasukan, total pengeluaran, dan lima transaksi terbaru. **Catatan privasi:** keterangan transaksi otomatis iuran **tidak boleh memuat nama anggota** (mis. "Iuran Oktober 2026"); tunggakan hanya terlihat Admin. Seeder saat ini masih memakai keterangan bernama ("Iuran Sinta Maharani Oktober"): ubah bersama Langkah E. |
| D3 | Admin/Editor ikut ditagih iuran? | Laravel: pembuat organisasi `is_billable=false`; pembuat tagihan di Langkah E hanya menagih anggota `is_billable` dan aktif. Belum ada keputusan untuk Admin/Editor yang ingin ikut membayar. |
| D4 | Admin menambah kontak dengan email akun yang sudah terverifikasi | Tidak ditautkan otomatis. Usulan: **undangan yang harus diterima** pemilik akun. |
| D5 | Serah-terima Admin, keluar dari organisasi, hapus organisasi | Belum ada UI. Pesan hapus akun sudah menyebutnya padahal fiturnya belum ada. |
| D6 | Nama organisasi boleh kembar? | Laravel: boleh (tidak ada aturan unik). |
| D7 | CSS prototipe vs Tailwind Breeze untuk Tahap 5 | Belum ditentukan. Aturan pemilik: **tampilan mengikuti halaman asli KasKita**, bukan desain baru. |
| D8 | Locale `id` dan timezone `Asia/Jakarta` | Sekarang `en` dan `UTC`. Belum diubah. |
| D9 | Aturan kode undangan (dipilih Pembuat di Langkah B, mudah diubah) | Masa berlaku **1/7/30 hari** (`Invitation::DURATIONS`); kode awal organisasi **7 hari** (`DEFAULT_DAYS`); boleh **banyak kode aktif**; kode **ditutup, tidak dihapus**. Ubah konstanta bila pemilik ingin lain. |

Bila langkah berikutnya butuh keputusan yang belum ada, **ajukan satu pertanyaan** dan tawarkan default yang aman (matriks bagian 6).

## 9. Masalah yang diketahui / belum dikerjakan

Urutan = prioritas kasar.

1. **Gerbang Policy baru terpasang di dashboard dan undangan.** Kemampuan lain (`viewReports`, `manageTransactions`, dst.) sudah didefinisikan tetapi **belum dipakai**; pasang gerbang di tiap controller baru, dengan tes yang menolak peran yang tidak berhak.
2. **Belum ada navigasi di dalam organisasi.** Tautan "Kelola undangan" sudah ditambahkan manual ke `organizations/dashboard.blade.php`, tetapi diletakkan **di luar kontainer halaman** (di antara `</x-slot>` dan `<div class="py-8">`), sehingga tampil tanpa lebar/jarak yang rapi. Pindahkan ke dalam `<div class="max-w-4xl ...">` bila mengganggu; menu/sidebar organisasi dibuat di Langkah H. Gaya kode `routes/web.php` (urutan `use`, indentasi grup) belum dirapikan Pint: jalankan `vendor/bin/pint routes/web.php`.
3. **`ActivityLog` baru ditulis untuk aksi undangan.** Membuat organisasi, bergabung, dan aksi modul lain belum tercatat. Pencatat saat ini privat di `InvitationController`; jadikan helper bersama di Langkah C.
4. **Non-anggota mendapat 403 untuk organisasi yang ada dan 404 untuk yang tidak ada**, sehingga ID organisasi bisa ditebak. Usulan: 404 untuk keduanya.
5. **`OwnerSeeder`** membuat owner dengan kata sandi tetap dan dipanggil tanpa syarat oleh `DatabaseSeeder`: **jangan** jalankan `db:seed` di server sungguhan sebelum diberi syarat lingkungan.
6. **Konfigurasi bawaan:** `APP_NAME=Laravel`, `APP_LOCALE=en`, timezone `UTC` (waktu di halaman Undangan tampil UTC), tidak ada folder `lang/`, halaman auth/profil Inggris, `welcome.blade.php` bawaan.
7. **Dokumen basi:** `README.md` masih menjelaskan prototipe statis; `AGENTS.md` bawaan installer (lihat aturan 5).
8. **Integritas lintas-organisasi** tidak dipaksa DB (kategori transaksi, anggota pada tagihan): wajib divalidasi di FormRequest. Untuk sumber daya anak di URL, `scopeBindings` sudah menutupnya (contoh: undangan).
9. **Gabung dua kali serentak** bisa menabrak indeks unik (500) karena tidak ada penanganan balapan.
10. **Pesan hapus akun Admin tunggal** menyuruh tindakan yang belum ada fiturnya (D5).
11. **MariaDB belum diuji lewat tes** (hanya SQLite): terutama kolasi dan FK pada migrasi yang diedit.
12. **Penautan email** mengandalkan email yang terverifikasi; `MAIL_MAILER=log` hanya menulis tautan ke log (cukup untuk pengembangan, tidak untuk produksi).
13. **`OrganizationPolicy::hasRole`** melakukan satu query keanggotaan per pemeriksaan; bila halaman memanggil banyak `@can`, pertimbangkan memakai `currentMember` dari middleware atau men-cache per request.
14. Pemilik pernah mengirim zip yang memuat `.env` (berisi `APP_KEY` dan `DB_PASSWORD`). `.gitignore` sudah benar; **jangan kirim `.env`**, dan ganti kuncinya bila pernah dibagikan.
15. **Kode undangan demo `BEM26KAS` permanen** (seeder): sengaja untuk demo, dan tampil sebagai "Tanpa batas waktu" di halaman Undangan. Kode di organisasi sungguhan sebaiknya selalu berbatas waktu.

## 10. Aturan kerja (ringkas)

- Baca bagian 0, 5, 6, dan 8 sebelum mengubah apa pun. Bandingkan dengan kode.
- Tambahkan **tes untuk setiap aturan baru**; tes lama harus tetap lulus. Tulis tes yang benar-benar gagal bila aturannya dilanggar dan **buktikan dengan uji mutasi** (rusak aturannya sementara, pastikan tes gagal, pulihkan).
- Jangan mengubah migrasi yang sudah ada tanpa memberi tahu pemilik; bila perlu, beri tahu bahwa `migrate:fresh` dibutuhkan. Untuk perubahan skema berikutnya, pertimbangkan migrasi baru.
- Validasi di FormRequest, otorisasi di Policy (`Gate::authorize` di controller, `authorize()` di FormRequest untuk aksi berinput), logika multi-tabel di `DB::transaction`.
- Sumber daya di dalam organisasi lewat URL `organisasi/{organization}/...` dengan `scopeBindings`; tulis tes bahwa ID milik organisasi lain menghasilkan 404.
- Lampiran (nota/bukti) di disk **privat** (`FILESYSTEM_DISK=local`), divalidasi tipe dan ukuran di server, diunduh lewat rute yang dicek Policy; jangan simpan sebagai data URL.
- Teks UI Bahasa Indonesia, nada ramah dan jelas; pesan galat memberi langkah berikutnya. Jangan menampilkan data keuangan di panel Owner/Dev.
- Jangan menyimpan rahasia nyata di repo, seeder, atau zip.
- Tampilan mengikuti **halaman asli KasKita**, bukan desain baru.
- Berkas bersama (`routes/web.php`, view dashboard, Policy, `OrganizationMember`): **jangan ditimpa** bila Pembuat tidak melihat versi terbaru; kirim potongan tempel atau minta isinya dulu.
- Saat memandu pemilik: jangan tempel potongan kode yang bisa bentrok dengan berkas yang sudah ada; minta isi berkas yang relevan dulu.

## 11. Riwayat progres

| Tanggal | Perubahan | Tes |
| --- | --- | --- |
| (sebelum 3 Nov 2026) | Prototipe statis (commit `4a99c24`): alur, peran, tampilan. Tahap 1 (ERD) dan 2 (11 tabel, 9 model, relasi) di Linux Mint + MariaDB; seeder demo; struktur repo dirapikan (Laravel di akar, prototipe ke `prototipe-statis/`); Breeze dipasang (Node 22); `OrganizationController`, `EnsureOrganizationMember`, dashboard organisasi. | 1 dari 25 lulus (tes bawaan rusak) |
| **3 Nov 2026** | **Audit + perbaikan fondasi** (`kaskita-perbaikan.zip`): `HasFactory`; `MustVerifyEmail` + penautan hanya setelah verifikasi (`LinkPendingMemberships`); email kontak huruf kecil; akun dihapus menonaktifkan keanggotaan; Admin tunggal tak bisa hapus akun; kolom pencatat `nullOnDelete`; `Organization::purge()`; halaman `/organisasi` lengkap; `/dashboard` → `/organisasi`; `composer.json` PHP `^8.4`; rute `/organisasi` ganda di `routes/auth.php` dihapus; `platform_role` tidak mass-assignable; seeder `forceCreate` + terverifikasi; Pint. | **62 lulus** |
| **5 Nov 2026** | **Langkah A: Policy per peran.** `OrganizationMember::hasRole()` + konstanta peran; `OrganizationPolicy` (9 kemampuan, matriks bagian 6); `Gate::authorize('viewDashboard')` di `OrganizationDashboardController`; `OrganizationPolicyTest` (4 tes). **Keputusan D2:** Anggota boleh melihat dashboard (hanya lihat); **D1:** default prototipe. Pemilik memasang zip 3 Nov, `migrate:fresh --seed`, dan mencoba login/dashboard di browser. | **66 lulus** |
| **6 Nov 2026** | **Langkah B: kelola undangan** (`kaskita-langkah-b.zip`). **Baru:** `InvitationController` (index/store/close/reopen/regenerate), `StoreInvitationRequest`, `invitations/index.blade.php`, `InvitationManagementTest` (15 tes). **Diubah:** `Invitation` (`generateCode`, `issue`, `status`, `isClosed`, `isExpired`, konstanta `ALPHABET/DURATIONS/DEFAULT_DAYS`); `OrganizationController::store` (kode awal berlaku 7 hari lewat `Invitation::issue`; `newCode()` privat dihapus). **Dipasang manual oleh pemilik (tidak ada di zip):** lima rute + `->scopeBindings()` di `routes/web.php`, dan tautan "Kelola undangan" di view dashboard organisasi (lihat `PASANG-LANGKAH-B.txt` di zip). Tanpa migrasi baru. **Verifikasi Pembuat:** di sandbox yang memakai Policy **pengganti** (matriks bagian 6) suite lulus 77 tes (tanpa 4 tes Policy milik pemilik); lima uji mutasi masing-masing menggagalkan tepat satu tes; Pint bersih. **Dikonfirmasi pemilik:** `php artisan test` di repo asli = **81 lulus (556 assertion)**; lima rute dan `->scopeBindings()` terpasang di `routes/web.php`; tautan "Kelola undangan" terpasang di view dashboard. **Belum diverifikasi:** tampilan halaman Undangan di browser, tombol Salin, MariaDB. | **81 lulus (556 assertion)** di repo pemilik |

Perintah yang harus dijalankan pemilik setelah memasang serahan: ekstrak zip menimpa proyek (bila ada) → tempel potongan manual yang disebut → `composer update --lock` (bila `composer.json` berubah) → `php artisan migrate:fresh --seed` (bila migrasi berubah) → `php artisan optimize:clear` → `php artisan test`. **Pemilik melaporkan hasil `php artisan test` ke Claude; catat angka sebenarnya di bagian 11.**

## 12. Langkah berikutnya (urut; satu langkah per giliran)

**Langkah A — Policy dan pembatasan peran: SELESAI.** Sisa: pasang gerbang kemampuan lain saat modulnya dibuat.

**Langkah B — Kelola undangan: SELESAI** (tes hijau di repo pemilik: 81 lulus). Sisa kecil: coba halaman `/organisasi/{id}/undangan` di browser (terbuka untuk Admin, 403 untuk Editor/Anggota) dan catat hasilnya di bagian 11.

**Langkah C — Kelola anggota** ← *berikutnya*
`MemberController` (gerbang `manageMembers`): daftar, tambah/ubah kontak, ubah peran Anggota↔Editor (+ promosi Admin bila D5 diputuskan), nonaktifkan/keluarkan (jangan hapus baris yang punya tagihan), keluar sendiri. Terapkan aturan peran bagian 6 (pembuat tak bisa diturunkan, minimal satu Admin, tak mengubah peran sendiri) di Policy dan tes. Jadikan pencatat `ActivityLog` satu helper bersama dan pakai juga untuk "bergabung". Rute anak memakai `scopeBindings` (anggota milik organisasi lain = 404). **Sebelum menulis kode, minta pemilik menempel** `app/Models/OrganizationMember.php`, `app/Policies/OrganizationPolicy.php`, dan `routes/web.php` versi sekarang (sandbox Pembuat tidak punya Policy/`hasRole` asli).

**Langkah D — Kategori dan transaksi**
`CategoryController`, `TransactionController` + FormRequest (kategori harus milik organisasi yang sama), gerbang `manageTransactions`/`deleteTransactions`, kunci transaksi otomatis (`invoice_id`), soft delete (hapus: Admin), lampiran privat. Tes saldo, kunci, dan penolakan Anggota.

**Langkah E — Iuran**
`InvoiceController` + `InvoicePolicy`: buat tagihan per bulan (anggota `is_billable` dan aktif, tidak dobel per periode), unggah bukti (privat), setujui/tolak/lunas tunai/batalkan dalam `DB::transaction` yang membuat/menghapus transaksi otomatis kategori "Iuran". **Keterangan transaksi otomatis tanpa nama anggota** (D2). Anggota hanya melihat dan membayar tagihan miliknya sendiri.

**Langkah F — Laporan dan tutup buku**
Gerbang `viewReports`/`viewArrears`. Filter bulan dan minggu (minggu **dipotong di batas bulan**), saldo awal periode = `opening_balance` + transaksi sebelum periode, CSV dan PDF, `PeriodClosure` (bulan ditutup tidak bisa diedit).

**Langkah G — Panel Owner/Dev**
`/owner/*`: daftar organisasi (nama, tanggal dibuat, jumlah anggota, status), aktif/nonaktif; tes bahwa tidak menyentuh data keuangan.

**Langkah H — Tampilan (Tahap 5) dan penyempurnaan**
Masukkan view prototipe halaman demi halaman mengikuti halaman asli (putuskan D7), buat navigasi/sidebar organisasi (tautan Undangan dan lainnya), terjemahkan teks Breeze, atur D8, perbarui `README.md`, hapus `dashboard.blade.php` dan `welcome.blade.php` bawaan, beri syarat lingkungan pada `OwnerSeeder`. Uji di MariaDB. Pertimbangkan Pull Request ke `main` (perhatikan GitHub Pages).

**Peta halaman prototipe → Laravel (usulan):** `organisasi.html`→`organizations.index` (ada) · `dashboard.html`→`organizations.dashboard` (dasar, ada Policy) · `transaksi.html`→`TransactionController` · `iuran.html`→`InvoiceController` · `anggota.html`→`MemberController` + `InvitationController` (**undangan ada**) · `laporan.html`→`ReportController` · `pengaturan.html`→pengaturan organisasi + `CategoryController` · `owner.html`→`Owner\OrganizationController`.