> **ARSIP, bukan untuk ditempel utuh ke Claude.** Berisi aturan bisnis rinci (bagian 5), daftar tes (bagian 7), riwayat (bagian 11), dan masalah lama/terselesaikan (bagian 9) sampai Langkah E3 (12 Nov 2026).
> Bagian 0, 10, dan 12 di bawah **sudah usang**: yang berlaku ada di `CLAUDE.md`.
> Tempel hanya bagian yang relevan ke Claude bila diminta, misalnya "bagian 5, Iuran".

# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude. **Perbarui di setiap kemajuan** (bagian 2, 9, 11, 12 wajib).
> Terakhir diperbarui: **12 Nov 2026** · Tes: **168 lulus, 1047 assertion** (dijalankan pemilik di repo asli) · Bentuk proyek: Laravel 13 + Breeze (Blade).

## 0. Cara kerja (baca dulu)

Pemilik proyek memakai **beberapa sesi Claude** karena batas pemakaian. Sesi baru mana pun, termasuk yang tidak punya riwayat sebelumnya, bekerja dari berkas ini.

**Cara serah kode (berlaku sekarang):** Claude menulis kode **langsung di chat, per file, lengkap**, lalu pemilik menyalinnya. **Tidak ada zip dan tidak ada unggahan file.** Claude tidak melihat repo dan tidak bisa menjalankan tes; ia hanya tahu yang ditempel pemilik. Tidak boleh mengarang isi file yang belum dilihat: minta pemilik menempel file itu (atau pesan galatnya) dulu. Pemilik menempel file dengan `cat`; tempelan panjang bisa terpotong, jadi minta per kelompok kecil (2-3 file).

Aturan bersama:

1. **Sumber kebenaran = kode di repo + berkas ini.** Bila berbeda, kode yang benar, lalu perbaiki berkas ini. (Pernah terjadi di E3: kode sudah ditulis sesi lain sebelum berkas ini dicatat. **Selalu cek `git status` dan `ls` dulu sebelum menulis.**)
2. **Setiap kemajuan:** tambah satu entri di bagian 11, lalu perbarui bagian 2, 9, 12 dan angka tes di kepala berkas. Setelah satu langkah selesai, Claude menyerahkan teks `CLAUDE.md` baru yang utuh **sebagai teks di chat, bukan file**.
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

> Baca `CLAUDE.md` terlampir. Lanjutkan dari bagian 12 ("Langkah berikutnya"). Ikuti bagian 0 dan 10. Sebutkan file apa saja yang perlu kutempel sebelum kamu menulis kode, dan minta aku menjalankan `git status --short` serta `ls` untuk memastikan belum ada kode yang ditulis sesi lain. Bila ada keputusan di bagian 8 yang menghalangi, tanyakan satu pertanyaan saja. Tulis kode lengkap per file di chat (tanpa zip). Setelah selesai, beri teks `CLAUDE.md` baru yang utuh sebagai teks di chat (bukan file).

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
- Commit terakhir yang sudah di GitHub: `72ec924` ("Claude.md di perbarui, lanjut ke langkah E3"). **Langkah E3 dikomit dan di-push pemilik setelah serahan ini** (pesan komit usulan: "Langkah E3: pengajuan pembayaran, bukti bayar, setujui/tolak"); bila belum, `git status` akan menunjukkan file E3 belum dikomit. Sebelum `git add -A`, periksa `git status`: jangan sampai `.env` atau file catatan pribadi ikut.

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
| 3. Autentikasi dan sistem akses | **Sebagian** | Breeze, email wajib terverifikasi, middleware `org.member`, organisasi aktif lewat URL, `OrganizationPolicy` (11 kemampuan). Gerbang terpasang di dashboard, undangan, anggota, kategori, transaksi, iuran. Aturan "tidak menyentuh tagihan sendiri" terpasang (E2) dan berlaku juga untuk setujui/tolak (E3). **Belum:** panel Owner. |
| 4. Controller dan logika bisnis | **Sebagian** | Ada: Organization, OrganizationDashboard, Invitation (B), Member (C; E2: log `is_billable`), Category dan Transaction (D), **Invoice (E1-E3: buat tagihan, lunas tunai, batalkan, ajukan pembayaran, bukti, tarik, setujui, tolak)**, hapus akun di Profile. **Belum:** Report, Owner. |
| 5. View (Blade) | **Sebagian** | `organizations/{index,dashboard}`, `invitations/index`, `members/index`, `categories/index`, `transactions/{index,edit,_fields}`, `invoices/{index,_proof}`. Halaman auth/profil masih bahasa Inggris bawaan Breeze. |
| 6. Pengujian dan penyempurnaan | **Sebagian** | 168 tes lulus. Belum ada tes laporan. |

## 3. Struktur dan konvensi

```
app/Http/Controllers/   OrganizationController, OrganizationDashboardController, InvitationController,
                        MemberController, CategoryController, TransactionController, InvoiceController,
                        ProfileController, Auth/* (Breeze)
app/Http/Requests/      StoreInvitationRequest, StoreMemberRequest, UpdateMemberRequest,
                        CategoryRequest, TransactionRequest, StoreInvoiceRequest,
                        SubmitPaymentRequest, ProofRequest (turunan SubmitPaymentRequest), RejectInvoiceRequest
app/Support/            ActivityLogger            (ActivityLogger::record())
app/Http/Middleware/    EnsureOrganizationMember  (alias org.member, di bootstrap/app.php)
app/Listeners/          LinkPendingMemberships    (event Verified)
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice,
                        Invitation, InvitationUse, ActivityLog, PeriodClosure
app/Policies/           OrganizationPolicy
database/migrations/    2026_10_31_* + bawaan Laravel
database/seeders/       OwnerSeeder, DemoOrganizationSeeder (dipanggil DatabaseSeeder)
resources/views/        organizations/{index,dashboard} ; invitations/index ; members/index ; categories/index ;
                        transactions/{index,edit,_fields} ; invoices/{index,_proof} ; welcome.blade.php (bawaan)
routes/web.php          rute aplikasi ; routes/auth.php HANYA rute Breeze
tests/Feature/          lihat bagian 7
prototipe-statis/ , docs/TAHAP.txt
```

Konvensi:

- **Nama tabel/kolom/rute/nama rute Bahasa Inggris**; **URI dan teks UI Bahasa Indonesia** (`/organisasi`, `/transaksi`, `/iuran`). Pengecualian: kolom `tanggal` dan `period`.
- **Satu controller per modul**; logika multi-tabel di `DB::transaction`, idealnya di metode model (contoh: `Organization::purge()`, `Invitation::issue()`, `Invoice::settle()`).
- **Pola controller modul:** metode privat `done(...)` (redirect ke index dengan `status`) dan `refuse(...)` (redirect dengan `withErrors([...])` pada kunci modul); view menampilkan keduanya.
- **Uang** = bilangan bulat rupiah (kolom `bigint`). **Tanggal transaksi** = `date`; **periode iuran** = `char(7)` `'YYYY-MM'`.
- **Email** disimpan huruf kecil (mutator `OrganizationMember::email`); kosong = `NULL`.
- **Organisasi aktif ditentukan oleh URL**; semua rute organisasi di grup `org.member` dengan `{organization}` dan **`->scopeBindings()`** (parameter anak seperti `{invitation}`, `{transaction}`, `{member}` hanya dicari dalam organisasi di URL; ID organisasi lain = 404). Pengecualian: **`{invoice}`** dicari manual (`Invoice::inOrganization()`, rute memakai `->whereNumber('invoice')`), karena tagihan tidak punya `organization_id`.
- **Otorisasi:** `Gate::authorize('<kemampuan>', $organization)` di controller, `@can` di view (`$currentOrganization`, `$currentMember` dibagikan middleware). Aksi berinput: pemeriksaan di `FormRequest::authorize()`. Peran dicek lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_*`.
- **Jangan menaruh rute aplikasi di `routes/auth.php`**; `RoutingTest` menjaga rute ganda.
- Jalankan `vendor/bin/pint` pada file yang diubah.
- **Disk file:** `config/filesystems.php` bawaan Laravel: `FILESYSTEM_DISK=local` → disk `local` berakar `storage/app/private` (privat, cocok untuk bukti bayar); disk `public` ada tetapi **jangan dipakai untuk lampiran**. Bukti iuran memakai `Invoice::PROOF_DISK` (`local`) dan `Invoice::PROOF_DIR` (`proofs`).

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
- **`is_billable` ≠ `is_active`:** ditagih atau tidak, versus masih tercatat aktif. **`is_billable` tidak terkait peran**: Admin/Editor tidak punya tagihan sampai `is_billable` dicentang (halaman Anggota → Ubah data) dan tagihan bulan itu dibuat ulang.
- **`invoices` tidak punya `organization_id`** (dicapai lewat `member`). `transactions.category_id` tidak dipaksa DB satu organisasi: divalidasi di FormRequest (sudah di `TransactionRequest`).
- Kolom `role`, `status`, `type`, `method` string tanpa CHECK; validasi di aplikasi.
- `restrictOnDelete` pada `invoices.member_id`, `transactions.invoice_id`, `transactions.category_id` **disengaja**; hapus organisasi lewat `Organization::purge()`.
- Owner tidak punya tabel sendiri: `users.platform_role = 'owner'`.
- **Migrasi lama diedit di tempat.** Langkah B, D, E1, E2, E3 **tidak** menambah migrasi.

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

**Kelola anggota (C, E2)** — *C ditulis Pemandu, belum ditinjau sesi lain*

- Hanya Admin (`manageMembers`) kecuali `leave` (anggota aktif non-Admin). Peran yang bisa diatur hanya **Anggota ↔ Editor**; Admin tidak bisa diberikan atau dicabut lewat form.
- Pembuat organisasi, Admin, dan diri sendiri terlindungi dari perubahan peran dan penonaktifan. Kontak tanpa akun tidak bisa diberi peran.
- Anggota **dinonaktifkan, tidak dihapus**; reaktivasi mengembalikan akun jadi Anggota. Email anggota berakun mengikuti akunnya.
- **`is_billable` bisa diubah Admin** lewat "Ubah data" di halaman Anggota (termasuk barisnya sendiri; `UpdateMemberRequest` mewajibkannya). **E2:** perubahan nilainya dicatat tersendiri sebagai `member.billable_changed` (selain `member.updated`).
- Log lewat `ActivityLogger`: `member.created|updated|billable_changed|role_changed|deactivated|reactivated|left|joined`.

**Kategori dan transaksi (D)** — *tes hijau; belum ditinjau sesi lain*

- **Kategori:** hanya Admin (`manageSettings`). Nama dipangkas, wajib, maks 100, unik per organisasi tanpa peka huruf besar/kecil. Kategori `is_system` tidak bisa diubah atau dihapus. Kategori yang dipakai transaksi (termasuk soft-deleted) tidak bisa dihapus. Organisasi lain = 404. Log: `category.created|renamed|deleted`.
- **Transaksi:** semua anggota aktif melihat (`viewTransactions`); Admin/Editor mencatat dan mengubah (`manageTransactions`); hanya Admin menghapus (`deleteTransactions`, soft delete). `created_by` tidak berganti saat diubah.
- **Validasi (`TransactionRequest`):** `type` masuk|keluar; `category_id` milik organisasi dan **bukan sistem**; `amount` bulat 1 s.d. 100.000.000.000; `tanggal` `Y-m-d` min 2000-01-01; `description` maks 255.
- **Terkunci:** transaksi ber-`invoice_id` (`isLocked()`) tidak bisa diubah atau dihapus; halaman ubah dialihkan dengan pesan.
- **Daftar:** filter `month` (`Y-m`), `type`, `q` (LIKE); ringkasan mengikuti filter; 15 per halaman. Log: `transaction.created|updated|deleted`.

**Iuran (E1, E2, E3)** — *tes hijau (`InvoiceManagementTest` 23 + `InvoicePaymentTest` 18); browser: sebagian (lihat bagian 7). E3 ditulis sesi lain dan ditinjau sebagian oleh Pemandu (lihat bagian 11).*

- Rute (`org.member`): `invoices.index` (GET `/iuran`), `invoices.store` (POST `/iuran`), `invoices.pay_cash` (PATCH `/iuran/{invoice}/lunas-tunai`), `invoices.cancel` (PATCH `.../batalkan`), `invoices.approve` (PATCH `.../setujui`), `invoices.reject` (PATCH `.../tolak`), `invoices.submit` (POST `.../ajukan`), `invoices.withdraw` (PATCH `.../tarik`), `invoices.proof_download` (GET `.../bukti`), `invoices.proof_store` (POST `.../bukti`), `invoices.proof_destroy` (DELETE `.../bukti`). `{invoice}` dicari lewat `Invoice::inOrganization()` (organisasi lain = 404).
- **Hak (E2):** semua anggota aktif membuka halaman (`viewInvoices`). **Admin dan Editor** (`manageInvoices`) melihat semua tagihan satu periode (bawaan: terbaru; filter `period`), membuat tagihan, melunasi tunai, membatalkan pelunasan, menyetujui/menolak pengajuan. **Anggota** hanya melihat tagihan **miliknya**, semua bulan; tombolnya adalah aksi pemilik tagihan (di bawah).
- **Aturan tagihan sendiri (E2, diperluas E3):** `InvoiceController::isOwn()` (`member.user_id` = pengguna yang bertindak; kontak tanpa akun tidak pernah "milik sendiri"). `payCash`, `cancel` (pesan `OWN_INVOICE_MESSAGE`), `approve`, `reject` (pesan `OWN_VERIFY_MESSAGE`) menolak tagihan sendiri dengan galat `invoice` (bukan 403). View: tagihan sendiri bertanda "(tagihanmu)", tanpa tombol verifikasi, dengan keterangan "Menunggu diverifikasi pengelola lain" bila ada pengelola aktif lain, atau "Belum ada pengelola lain…" bila tidak ada; untuk tagihan sendiri yang sudah lunas: pembatalan hanya oleh pengelola lain. `$hasOtherManager` dihitung di `index`.
- **Buat tagihan:** satu periode (`YYYY-MM`, 2000-an) dan jumlah untuk semua anggota **aktif dan `is_billable`**; yang sudah punya tagihan periode itu dilewati (tidak dobel, jumlah lama tidak berubah). Setelah `is_billable` seseorang diaktifkan, tagihan bulan berjalan harus dibuat ulang. Log `invoice.generated`. `StoreInvoiceRequest::authorize()` memakai `manageInvoices`.
- **`Invoice::settle($id, $organization, $actor, ?$method, array $from)` (E3):** dipakai bersama "Lunas tunai" (`METHOD_TUNAI`, dari `belum`/`menunggu`) dan "Setujui" (`method` null = pertahankan cara yang diajukan, dari `menunggu`). Dalam `DB::transaction` + `lockForUpdate`: tagihan jadi `lunas`, `paid_at` = sekarang, `rejection_reason` null; membuat transaksi `masuk` kategori Iuran (`firstOrCreate` `['name'=>'Iuran','is_system'=>true]`) dengan `invoice_id`, `created_by` = pelaku, `tanggal` = hari ini, keterangan "Iuran {Bulan} {Tahun}" **tanpa nama anggota (D2)**. Mengembalikan `null` bila status asal tidak cocok (controller: galat "sudah lunas" / "sudah tidak menunggu verifikasi"). Log `invoice.paid_cash` / `invoice.approved`.
- **Batalkan pelunasan:** hanya untuk yang lunas; transaksi otomatis **dihapus permanen** (`forceDelete`, karena `transactions.invoice_id` unik); tagihan kembali `belum` (method/paid_at/rejection_reason/**proof_path** null; file bukti dihapus dari disk); pelunasan ulang bisa. Log `invoice.payment_cancelled`.
- **Pengajuan pembayaran (E3, D11):** hanya **pemilik tagihan** (`SubmitPaymentRequest::authorize()`: `findOrFail` lewat `inOrganization` → organisasi lain 404; bukan pemilik → 403). `submit` (POST): dari `belum` → `menunggu`, `method` `transfer`|`tunai` wajib, `proof` opsional (`file`, `mimes:jpg,jpeg,png,pdf`, `max:2048`), `rejection_reason` dihapus; file disimpan di `proofs/` disk privat dengan nama acak; bila status ternyata bukan `belum`, file yang baru diunggah dibuang dan galat tampil. Log `invoice.submitted` (metadata `has_proof`). Admin/Editor yang `is_billable` boleh mengajukan tagihannya sendiri.
- **Bukti (E3):** selama `menunggu`, pemilik boleh `storeProof` (tambah/ganti; `ProofRequest` mewajibkan berkas; file lama dihapus; log `invoice.proof_updated`), `destroyProof` (hapus bukti, pengajuan tetap menunggu; log `invoice.proof_removed`), dan `withdraw` (kembali `belum`, bukti dihapus; log `invoice.withdrawn`). Di luar `menunggu`, aksi-aksi ini ditolak dengan galat `invoice`. Setelah `lunas` bukti **terkunci** (batalkan pelunasan dulu; pembatalan menghapus bukti).
- **Tolak (E3):** `RejectInvoiceRequest` (`manageInvoices`; alasan wajib, dipangkas, maks 255). Dari `menunggu` → `belum`, `method`/`proof_path`/`paid_at` null, `rejection_reason` diisi, file bukti dihapus; pemilik melihat alasan dan boleh mengajukan ulang. Log `invoice.rejected`.
- **Unduh bukti (E3):** `proof` (GET): `viewInvoices`, lalu hanya pemilik tagihan atau `manageInvoices` (selain itu 403); file lewat `Storage::disk(PROOF_DISK)->download`, nama `bukti-iuran-{period}-{id}.{ext}`. Bila berkas tidak ada di disk: dialihkan dengan galat "Berkas bukti tidak tersedia." Partial `invoices/_proof` menampilkan "Unduh bukti", "Berkas bukti tidak tersedia", atau "Tanpa bukti".
- `Invoice`: konstanta `STATUS_*`, `METHOD_TUNAI`, `METHOD_TRANSFER`, `PROOF_DISK`, `PROOF_DIR`; `scopeInOrganization`, `isPaid()`, `proofAvailable()` (nama berkas ada **dan** berkas ada di disk), `settle()`, `labelFor('2026-10')` = "Oktober 2026", `periodLabel()`, relasi `member()` dan `transaction()`.
- Seeder: keterangan transaksi iuran "Iuran Oktober 2026" (tanpa nama). `proof_path` tagihan Budi = `proofs/demo-bukti-budi.svg`, berkas fiktif yang tidak ada di disk (tampil "Berkas bukti tidak tersedia", tidak error). Nadia dan Rafi di seeder **tidak ditagih** (D12).

### Aturan bisnis F2: Tutup buku
- Hanya **Admin** yang boleh menutup dan membuka kembali (`[POLICY]`). Editor dan Anggota ditolak. Admin organisasi lain tidak bisa menyentuh organisasi ini; penutupan satu organisasi tidak memengaruhi organisasi lain.
- Hanya bulan **lampau** yang bisa ditutup: bulan berjalan dan bulan depan ditolak. Format periode tidak valid ditolak. Menutup dua kali ditolak tanpa data ganda (unik `organization_id`+`period`).
- Membuka kembali bulan yang belum ditutup ditolak. Tutup dan buka kembali dicatat lewat `ActivityLogger` (`[LOG-TUTUP]`, `[LOG-BUKA]`).
- **Bulan tertutup menolak:** membuat transaksi, mengubah, memindahkan transaksi ke bulan itu (atau dari bulan itu), membuka form ubah, dan menghapus. Bulan lain tetap bisa dipakai.
- **Iuran:** lunas tunai dan setujui ditolak bila bulan **hari ini** tertutup (transaksi otomatis memakai tanggal hari ini). Batalkan pelunasan ditolak bila **transaksi pembayarannya** berada di bulan tertutup. Anggota **tetap boleh mengajukan** pembayaran walau bulan tagihan tertutup, karena belum membuat transaksi.
- Penolakan memakai `PeriodClosedException` dengan pesan jelas (jawaban atas pertanyaan desain F2: tolak, bukan alihkan tanggal).
- Halaman Laporan menampilkan status tutup; tombol "Tutup" hanya untuk Admin pada bulan lampau yang masih terbuka; tombol "Buka kembali" hanya untuk Admin.

## 6. Peran dan hak akses

**Matriks yang sudah diterapkan di `OrganizationPolicy`** (semua = keanggotaan aktif):

| Kemampuan | Admin | Editor | Anggota | Bukan anggota |
| --- | :-: | :-: | :-: | :-: |
| `viewDashboard` | ya | ya | ya (D2) | – |
| `viewTransactions` — terpasang | ya | ya | ya | – |
| `viewInvoices` — terpasang (Admin/Editor semua, Anggota hanya miliknya) | ya | ya | ya | – |
| `viewReports` | ya | ya | – | – |
| `viewArrears` | ya | – | – | – |
| `manageTransactions` — terpasang | ya | ya | – | – |
| `deleteTransactions` — terpasang | ya | – | – | – |
| `manageInvoices` — terpasang (**Admin + Editor**, kecuali tagihan sendiri) | ya | ya | – | – |
| `manageMembers` — terpasang | ya | – | – | – |
| `manageInvitations` — terpasang | ya | – | – | – |
| `manageSettings` — terpasang | ya | – | – | – |

`OrganizationPolicyTest` memeriksa seluruh matriks (11 kemampuan). Pengajuan pembayaran dan bukti bukan kemampuan Policy: itu hak **pemilik tagihan** (dicek di `SubmitPaymentRequest` dan `isOwn`).

**Aturan peran (diterapkan di Langkah C):** pembuat organisasi tidak bisa diturunkan atau dikeluarkan; tak seorang pun mengubah perannya sendiri; kode undangan hanya memberi Anggota. **Minimal satu Admin** terjamin tidak langsung (Admin tidak bisa diturunkan atau keluar); **harus dirancang ulang** bila promosi Admin dibuat (D5).

**Aturan Iuran (E2-E3):** tak seorang pun melunasi, memverifikasi (setujui/tolak), atau membatalkan pelunasan tagihan yang `member.user_id`-nya dirinya sendiri; tagihan pengelola diverifikasi pengelola lain. Pemilik tagihan (termasuk pengelola yang ditagih) tetap boleh mengajukan dan mengurus bukti tagihannya sendiri. Organisasi tanpa pengelola kedua membuat tagihan pengelola menggantung: halaman Iuran menampilkan pesan jelas. Kolusi dua pengelola tidak bisa dicegah kode; yang tersedia hanya jejak di riwayat aktivitas dan transaksi yang terlihat semua anggota.

**Owner/Dev** punya controller/rute terpisah (`/owner/*`) yang **tidak pernah** menyentuh `transactions`, `invoices`, atau lampiran, dengan tes yang memastikannya. Pemegang server/database tetap bisa membaca data lewat alat database; yang dijamin hanya tidak adanya fitur mengintip, kata sandi ter-hash, dan panel berisi metadata.

## 7. Data demo dan pengujian

**Seeder** (`php artisan migrate:fresh --seed`). Kata sandi semua akun: `KitaDemo2026!`; semua terverifikasi. Blok "Akun demo" di `DemoOrganizationSeeder` hanya satu (dicek).

| Peran | Email | Ditagih |
| --- | --- | :-: |
| Owner/Dev | `owner@kaskita.demo` | – |
| Admin (Nadia Putri) | `admin@kaskita.demo` | tidak |
| Editor (Rafi Pratama) | `editor@kaskita.demo` | tidak |
| Anggota (Dimas Saputra) | `anggota@kaskita.demo` | ya |
| Kontak tanpa akun | `sinta@`, `budi@`, `citra@` `kaskita.demo` | ya |

Organisasi **BEM Fakultas Teknologi**: saldo awal 500.000, iuran 25.000, kode `BEM26KAS` (permanen), kategori Iuran/Donasi/Konsumsi/Perlengkapan/Transportasi, 6 transaksi (2 otomatis dari iuran), 4 tagihan 2026-10: Sinta lunas (transfer), Dimas belum, Budi menunggu (transfer, bukti fiktif), Citra lunas (tunai). Saldo awal dashboard = Rp 455.000. Seeder tidak idempotent. **Peran akun demo bisa berubah bila pemilik mengubahnya di halaman Anggota**; `migrate:fresh --seed` mengembalikannya. **Untuk mendemokan aturan tagihan sendiri:** Admin mencentang "Ditagih" pada baris Nadia dan Rafi (Anggota → Ubah data), lalu membuat tagihan 2026-10 lagi (yang sudah ada dilewati). **Untuk mendemokan pengajuan bayar:** masuk sebagai Dimas (`anggota@kaskita.demo`), buka Iuran, "Saya sudah bayar"; lalu masuk sebagai Rafi/Nadia untuk menyetujui atau menolak. Tagihan Budi (menunggu, tanpa akun) bisa disetujui/ditolak pengelola, bukti fiktifnya tampil "tidak tersedia".

**Tes** (`php artisan test`; **168 lulus, 1047 assertion**): Auth Breeze (18; login/daftar mengalihkan ke `organizations.index`); Example (2); `OrganizationTest` (18); `MembershipLinkingTest` (7); `AccountDeletionTest` (7); `OrganizationPurgeTest` (2); `RoutingTest` (3); `ProfileTest` (5); `OrganizationPolicyTest` (4, matriks 11 kemampuan); `InvitationManagementTest` (15); `MemberManagementTest` (19); `CategoryManagementTest` (11); `TransactionManagementTest` (14); `InvoiceManagementTest` (23: E1 + E2); `MemberBillableTest` (2); **`InvoicePaymentTest` (18, E3, memakai `Storage::fake('local')`: ajukan tanpa dan dengan bukti (tersimpan privat), jenis/ukuran/metode tidak valid, anggota lain tidak bisa mengajukan, organisasi lain 404, tidak bisa ajukan dua kali, ganti bukti menghapus file lama, hapus bukti, tarik pengajuan + file dihapus, anggota lain tidak bisa mengurus bukti/menarik, unduh hanya pemilik dan pengelola, berkas hilang dialihkan dengan pesan, Editor menyetujui + transaksi dibuat, Anggota biasa tidak bisa setujui/tolak, tolak butuh alasan lalu ajukan ulang, pengelola yang ditagih tidak bisa menyetujui/menolak pengajuannya sendiri tetapi pengelola lain bisa, bukti terkunci setelah lunas dan pembatalan membersihkannya, tampilan tombol dan label verifikator)**.

Catatan pengujian:

- Tes memakai SQLite di memori; aplikasi memakai MariaDB. Kolasi dan FK belum diuji di MariaDB. Penyimpanan file di tes memakai `Storage::fake`; disk sungguhan belum diuji.
- **Browser:** pemilik sudah mencoba login, daftar, `/organisasi`, dashboard, halaman Transaksi, dan **sebagian E2**: Dimas (Anggota yang ditagih; sempat Editor) tidak bisa melunasi tagihannya sendiri. **Belum dicoba:** seluruh alur E3 di browser (ajukan, bukti, unduh, setujui, tolak, tarik), skenario Admin–Editor saling memverifikasi dan keterangan "(tagihanmu)", alur Transaksi lengkap, Kategori, Undangan (tombol Salin hanya jalan di `localhost`/`127.0.0.1`/HTTPS), Anggota, tombol Keluar, alur Iuran E1 lengkap. Halaman Transaksi sempat tampak melebar penuh dan tanpa gaya (masalah 23).
- **Uji mutasi:** E1 satu kali (Gate `manageInvoices` di `payCash`; sudah dipulihkan). **E2 dan E3 belum dilakukan** (saran E3: komentari blok `isOwn` di `approve`, lalu `reject`; juga pemeriksaan `abort_unless($this->isOwn...)` di `withdraw`/`destroyProof`; harapannya tepat satu tes gagal masing-masing). Langkah D belum. Opsional (aturan 10).
- Verifikasi middleware rute: `route:list -v` dan `RoutingTest`.

## 8. Keputusan pemilik

| # | Pertanyaan | Keputusan / keadaan |
| --- | --- | --- |
| D1 | Hak **Editor** | **Diterapkan (E2, E3).** Editor mencatat/mengubah transaksi, membaca laporan tanpa tunggakan, dan mengelola Iuran (buat tagihan, lunas tunai, batalkan, setujui/tolak pengajuan) **kecuali tagihannya sendiri**. Pengaturan, anggota, undangan, kategori, hapus transaksi tetap hanya Admin. |
| D2 | Anggota melihat dashboard? | **Boleh, hanya lihat** (dashboard dan daftar transaksi). Keterangan transaksi otomatis iuran **tanpa nama anggota**; tunggakan hanya terlihat Admin. Diterapkan di E1 dan seeder. |
| D3 | Admin/Editor ikut ditagih? | **Diterapkan (E2).** Admin/Editor yang ingin membayar diset `is_billable = true` (halaman Anggota → Ubah data) dan membayar seperti anggota lain, tanpa memverifikasi tagihannya sendiri. Satu akun, bukan dua. Bawaan pembuat organisasi tetap `is_billable=false`. Dosen pengawas = Anggota dengan `is_billable=false`. |
| D4 | Admin menambah kontak dengan email akun terverifikasi | Tidak ditautkan otomatis. Usulan: undangan yang harus diterima. |
| D5 | Serah-terima Admin, promosi ke Admin, hapus organisasi | Belum ada UI. Admin belum bisa keluar. Jaminan "minimal satu Admin" harus dirancang ulang. |
| D6 | Nama organisasi boleh kembar? | Boleh. |
| D7 | CSS prototipe vs Tailwind Breeze | Belum ditentukan. Aturan pemilik: tampilan mengikuti halaman asli KasKita. |
| D8 | Locale `id` dan timezone `Asia/Jakarta` | Sekarang `en` dan `UTC`. Belum diubah. |
| D9 | Aturan kode undangan | 1/7/30 hari; kode awal 7 hari; banyak kode aktif; ditutup, tidak dihapus. |
| D10 | Lampiran | **Bukti bayar iuran: diterapkan (E3).** Nota transaksi manual (`transactions.attachment_path`) menyusul (Langkah H). |
| D11 | Aturan bukti bayar | **Diterapkan (E3).** Anggota menekan "Saya sudah bayar" pada tagihannya, memilih transfer/tunai; **bukti opsional**. Selama `menunggu`: bukti boleh ditambah, diganti, dihapus, atau pengajuan ditarik (kembali `belum`). Verifikator menyetujui (`lunas` + transaksi otomatis) atau menolak dengan alasan (kembali `belum`, alasan tampil, boleh ajukan ulang). Setelah `lunas`, bukti terkunci (batalkan pelunasan dulu). Bukti di disk **privat**, jpg/png/pdf maks 2 MB, nama acak, unduh lewat rute yang dicek hak (pemilik tagihan atau verifikator), file lama dihapus saat diganti. Label "Tanpa bukti" untuk verifikator. |
| D12 | Seeder: Nadia dan Rafi ikut ditagih? | **Tidak, dibiarkan** (11 Nov): tetap `is_billable=false`. Demo aturan tagihan sendiri lewat UI (bagian 7). Jangan mengubah seeder untuk ini kecuali pemilik meminta. |
| D13 | Format laporan (12 Nov) | **CSV saja.** PDF **tidak dikerjakan** (ditunda, bukan dibatalkan; bisa ditambah nanti bila pemilik meminta, butuh paket seperti `dompdf` dan perubahan `composer.json`). Langkah F tidak boleh mengubah `composer.json`. |

Bila langkah berikutnya butuh keputusan yang belum ada, **ajukan satu pertanyaan** dan tawarkan default yang aman.

## 9. Masalah yang diketahui / belum dikerjakan

1. ~~Aturan tagihan sendiri dan hak Editor di Iuran~~ **Selesai di E2.**
2. ~~Pengajuan bayar dan bukti~~ **Selesai di E3** (tes hijau; browser belum).
3. ~~`is_billable` dari halaman Anggota~~ **Sudah ada** (E2 menambah log).
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
17. **Kode Langkah C (Pemandu), D, E1, E2, E3 belum ditinjau sesi lain** (E3 ditulis sesi lain dan dibaca sebagian oleh Pemandu: controller, model, view, request, rute dibaca; isi `InvoicePaymentTest` hanya nama tesnya). Uji mutasi D, E1 (sebagian), E2, E3 belum penuh (opsional).
18. **Lampiran nota transaksi belum ada** (Langkah H; D10).
19. **Pencarian transaksi (`q`)** memperlakukan `%` dan `_` sebagai wildcard; perbaikan: `addcslashes($filters['q'], '%_\\')`. Dampak kecil.
20. **Form "Tambah transaksi" ikut terbuka** bila filter `type` tidak valid (kunci galat sama). Kosmetik.
21. **Belum dicoba di browser:** Undangan, Anggota, Keluar, Kategori, alur Transaksi, alur Iuran (E1 dan **seluruh E3**) selain yang tercatat di bagian 7.
22. **Pelunasan tunai/persetujuan mengisi `tanggal` transaksi dengan hari ini** (`now()`), bukan tanggal pembayaran yang sebenarnya; tidak ada input tanggal. Zona waktu UTC (D8) bisa menggeser tanggal di sekitar tengah malam WIB.
23. **Tampilan halaman Transaksi tampak melebar penuh dan tanpa kontainer** pada tangkapan layar pemilik. Dugaan: `npm run dev` tidak berjalan atau CSS belum ter-build. **Belum dikonfirmasi.**
24. ~~Organisasi tanpa pengelola kedua~~ **Ditangani di E2** (pesan di halaman Iuran).
25. ~~Seeder: Nadia dan Rafi tidak ditagih~~ **Diputuskan dibiarkan (D12).**
26. ~~Seeder tertempel dua kali~~ **Dicek: hanya salah tempel.**
27. ~~Komentar basi di `routes/web.php`~~ **Sudah diperbarui di E3.**
28. **Keterangan menyesatkan di view Iuran:** tagihan sendiri milik pengelola berstatus `belum` menampilkan "Menunggu diverifikasi pengelola lain" padahal belum diajukan. Perbaikan kecil: tampilkan keterangan itu hanya bila `status === 'menunggu'`; untuk `belum` tampilkan form "Saya sudah bayar" saja. (Ditemukan 12 Nov saat tinjauan E3; belum diperbaiki.)
29. **"Lunas tunai" pada tagihan `menunggu`** mengubah `method` jadi `tunai` dan membiarkan `proof_path` tersimpan (bukti ikut tampil di tagihan lunas). Masih konsisten dengan E1, tetapi bisa dipertimbangkan: hapus bukti atau pertahankan `method` pengajuan.
30. **`authorize()` di `SubmitPaymentRequest` dan controller sama-sama mencari tagihan** (satu query ganda). Dampak kecil.
31. **Sisa file bukti bila proses gagal di tengah:** `submit`/`storeProof` menyimpan file sebelum transaksi DB dan membuangnya bila status tidak cocok; kegagalan mendadak di antara keduanya (mis. error DB) bisa meninggalkan file yatim di `storage/app/private/proofs`. Dampak kecil; pertimbangkan perintah pembersih berkala.

## 10. Aturan kerja (ringkas)

- Baca bagian 0, 5, 6, 8 sebelum mengubah apa pun; bandingkan dengan kode. **Mulai dengan `git status --short` dan `ls` (aturan 1).**
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
| 11 Nov | **Langkah E1:** `InvoiceController`, `StoreInvoiceRequest`, `invoices/index`, `InvoiceManagementTest` (16); `Invoice` (konstanta, `inOrganization`, `labelFor`); Policy `viewInvoices`; `routes/web.php` (+4 rute); dashboard (+tautan Iuran); seeder (keterangan tanpa nama). Satu uji mutasi berhasil. Kesepakatan desain tercatat di bagian 8. | 141 (899) |
| 11 Nov | **Langkah E2:** Policy `manageInvoices` = Admin + Editor; `isOwn()` dan penolakan tagihan sendiri; `$hasOtherManager`; `member.billable_changed`; `MemberBillableTest` (2); +7 tes Iuran. Dikonfirmasi pemilik. Uji mutasi E2 belum. | 150 (955) |
| 11 Nov | **Penutup E1–E2:** dikomit (`d1f22e8`). Keputusan D12. | 150 (955) |
| 12 Nov | **Langkah E3** (kode ditulis sesi Claude lain, tidak tercatat saat itu; **ditemukan dan ditinjau sebagian oleh Pemandu di sesi ini**): `InvoiceController` (+`approve`, `reject`, `submit`, `withdraw`, `proof`, `storeProof`, `destroyProof`; `cancel` ikut membersihkan bukti); `Invoice` (`METHOD_TRANSFER`, `PROOF_DISK`/`PROOF_DIR`, `proofAvailable()`, `settle()` dipakai bersama lunas tunai dan setujui); request baru `SubmitPaymentRequest`, `ProofRequest`, `RejectInvoiceRequest`; view `invoices/index` (form ajukan, bukti, setujui/tolak) + partial `_proof`; `routes/web.php` (+7 rute, komentar diperbarui); `InvoicePaymentTest` (18). Pemilik menjalankan Pint (15 file bersih) dan seluruh tes. **Dikonfirmasi pemilik: 168 lulus (1047).** Tidak ada migrasi baru. **Belum:** uji browser E3, uji mutasi E3, tinjauan isi `InvoicePaymentTest`. Masalah baru 28–31 dicatat. | **168 (1047)** |
| 12 Nov | **Keputusan D13:** laporan hanya CSV, PDF ditunda. Langkah F dibagi F1 (laporan layar, CSV, tunggakan) dan F2 (tutup buku). Tidak ada perubahan kode. | 168 (1047) |
| 12 Nov | **Langkah F1:** `ReportController` (`index`, `download`), view `reports/index`, `ReportTest` (16); `routes/web.php` (+2 rute `/laporan`, `/laporan/unduh`); dashboard (+tautan Laporan, `@can('viewReports')`). Laporan Admin + Editor; tunggakan hanya Admin (`arrears` = `null` untuk Editor); filter bulan dan minggu (Senin-Minggu, dipotong batas bulan); saldo awal/akhir periode; CSV (BOM, netralisasi rumus, log `report.exported`). Tidak ada migrasi baru. Dikonfirmasi pemilik: 184 lulus (1132). **Belum:** uji browser, CSV di Excel/LibreOffice, uji mutasi F1, tinjauan sesi lain. Keputusan D14 (di `CLAUDE.md` bagian 8). | **184 (1132)** |
| 12 Nov | **Langkah F2 (tutup buku):** BARU `PeriodClosureController` (`store`, `destroy`), `ClosePeriodRequest`, `app/Exceptions/PeriodClosedException`, `PeriodClosureTest` (19). UBAH `TransactionController`, `TransactionRequest`, `InvoiceController`, `ReportController`, `Invoice` (`settle()` dan pembatalan), `Organization` (pemeriksaan periode tertutup), `OrganizationPolicy` (+`[POLICY]`, Admin), `routes/web.php` (+2 rute: `periods.close` POST `/laporan/tutup`, `periods.reopen` DELETE `/laporan/tutup/{period}`), view laporan (status tutup, tombol tutup/buka). Tidak ada migrasi baru (tabel `period_closures` sudah ada dari awal). Admin menutup bulan lampau; bulan tertutup menolak tambah/ubah/pindah/hapus transaksi dan pelunasan/persetujuan/pembatalan iuran yang menyentuh bulan itu. Pint merapikan 6 berkas. Dikonfirmasi pemilik: 203 lulus (1207). **Belum:** uji browser, uji mutasi F2 (otorisasi dan isolasi lintas organisasi), tinjauan sesi lain. Keputusan D15 (di `CLAUDE.md` bagian 8). | **203 (1207)** |

Perintah pemilik setelah menyalin serahan: salin file → `composer update --lock` (bila `composer.json` berubah) → `php artisan migrate:fresh --seed` (bila migrasi atau seeder berubah) → `php artisan optimize:clear` → `php artisan test`. Pemilik melaporkan baris `Tests: N passed (M assertions)`; catat angka sebenarnya di bagian 11.

## 12. Langkah berikutnya (urut; satu langkah per percakapan)

**Langkah A–D: SELESAI** (D kecuali lampiran nota; lihat D10 dan masalah 18). **E1, E2, E3: SELESAI** (tes hijau; E3 belum dicoba di browser dan belum lewat uji mutasi).

**Sebelum Langkah F (opsional, sangat singkat, bisa dilakukan sendiri oleh pemilik):**
- Coba alur E3 di browser (bagian 7: Dimas mengajukan dengan bukti → Rafi mengunduh dan menyetujui; ulangi dengan menolak; tagihan Budi "bukti tidak tersedia"). Laporkan bila ada yang aneh.
- Perbaiki masalah 28 (keterangan menyesatkan) bila mengganggu: perubahan kecil di `invoices/index.blade.php`, bisa digabung ke awal percakapan Langkah F sebagai "langkah 0".

**Langkah F — Laporan dan tutup buku** ← *berikutnya*
- Gerbang `viewReports` (Admin + Editor) dan `viewArrears` (hanya Admin). Filter bulan dan minggu (minggu dipotong di batas bulan), saldo awal periode = `opening_balance` + transaksi sebelum periode, ringkasan pemasukan/pengeluaran per kategori, tunggakan (tagihan `belum` dan `menunggu`; hanya Admin), unduh CSV (tanpa PDF, lihat D13), `PeriodClosure` (bulan ditutup tidak bisa diedit; menyentuh `TransactionController` dan Iuran, termasuk pelunasan/pembatalan yang membuat atau menghapus transaksi di periode tertutup).
- **Keputusan sudah ada (D13):** CSV saja, tanpa PDF. Urutan: **F1** = laporan layar + CSV + tunggakan; **F2** = tutup buku (`PeriodClosure`). Tidak ada keputusan yang menghalangi; tidak perlu bertanya ke pemilik.
- **Sebelum menulis kode, minta pemilik menjalankan `git status --short; ls app/Http/Controllers tests/Feature` lalu menempel (per kelompok kecil):**
  - Kelompok 1: `routes/web.php`, `app/Policies/OrganizationPolicy.php`, `app/Models/Transaction.php`
  - Kelompok 2: `app/Http/Controllers/TransactionController.php`, `app/Http/Controllers/OrganizationDashboardController.php`, `app/Models/PeriodClosure.php`
  - Kelompok 3 (bila perlu): `resources/views/organizations/dashboard.blade.php`, `app/Models/Organization.php`
  - Contoh perintah: `for f in <file1> <file2> <file3>; do echo "=== $f ==="; cat "$f"; echo; done`
- Tes: gerbang peran (Anggota 403, Editor tanpa tunggakan), saldo awal periode, pemotongan minggu di batas bulan, isi CSV, isolasi organisasi lain (404), dan bahwa bulan ditutup menolak ubah/hapus transaksi.

**Langkah G — Panel Owner/Dev**
## Langkah G: Panel Owner/Dev (12 Nov 2026) · belum ditinjau sesi lain

**Hasil:** tes 203 → 217 lulus (1260 assertion, dijalankan pemilik). Tanpa migrasi, tanpa perubahan Policy, tanpa perubahan `composer.json`.

**File baru:**
- `app/Http/Middleware/EnsureOwner.php` (alias `owner`): 403 bila bukan `isOwner()`.
- `app/Http/Controllers/OwnerOrganizationController.php`: `index`, `deactivate`, `activate`.
- `resources/views/owner/organizations/index.blade.php`
- `tests/Feature/OwnerPanelTest.php` (10 tes), `tests/Feature/OwnerSeparationTest.php` (4 tes).

**File diubah:** `bootstrap/app.php` (alias `owner`), `routes/web.php` (grup `owner/organisasi`), `layouts/navigation.blade.php` (tautan "Panel Owner" hanya untuk Owner, desktop dan ponsel), `OrganizationController` (Owner dialihkan/ditolak, lihat D17).

**Aturan bisnis baru:**
- Rute: `GET /owner/organisasi/daftar`, `PATCH /owner/organisasi/{organization}/nonaktifkan|aktifkan`. Middleware `auth`, `verified`, `owner`. Bukan anggota organisasi mana pun, jadi tidak lewat `org.member`.
- Panel hanya menampilkan metadata: nama, tanggal dibuat, jumlah anggota aktif **bersakun** (kontak tanpa akun tidak dihitung), status, penanda "Tanpa Admin" (tidak ada Admin aktif), ringkasan jumlah organisasi (aktif/nonaktif) dan akun terdaftar (tanpa akun Owner). Query hanya memilih `id, name, status, created_at`; saldo, transaksi, tagihan, `payment_info`, nama dan email anggota tidak dibaca. Ada tes yang memastikan itu.
- Nonaktifkan/aktifkan dilog lewat `ActivityLogger::record()` (`organization.deactivated` / `organization.activated`, metadata from/to). Status yang sama ditolak tanpa log.
- Efek nonaktif memakai perilaku lama `EnsureOrganizationMember`: anggota mendapat 403 "Organisasi ini sedang dinonaktifkan." Data tidak dihapus. Kode undangan organisasi nonaktif juga ditolak (sudah ada sebelumnya).

**Keputusan:**
- **D16:** tanpa enkripsi end-to-end. Alasan: server harus membaca data untuk saldo, filter, pencarian, tunggakan, dan CSV; E2EE merusak semua itu dan menambah masalah kunci. Perlindungan yang dipakai: kata sandi di-hash, lampiran di disk privat, panel Owner tanpa data keuangan. Pengelola server yang memegang akses database secara teknis tetap bisa membaca data (bergantung pada kebijakan, bukan kriptografi). Opsional bila dipakai publik: cast `encrypted` untuk `payment_info`, HTTPS, enkripsi disk/backup.
- **D17:** Owner platform terpisah dari peran organisasi. Owner yang membuka `/organisasi` dialihkan ke panel; membuat organisasi dan bergabung dengan kode ditolak 403. Alasan: pembuat organisasi otomatis Admin yang bisa melihat data keuangan, bertentangan dengan panel Owner tanpa data keuangan. Untuk mencoba peran Admin, pakai akun demo biasa.

**Kejadian:** `RoutingTest` sempat gagal karena rute indeks Owner ditulis `Route::get('/')` di dalam grup berawalan; tes itu memindai teks `routes/web.php` tanpa memperhitungkan `prefix()` dan menganggapnya sama dengan `GET /`. Diperbaiki dengan path unik `/daftar`. **Pelajaran:** jangan menulis `Route::get('/')` di grup berawalan.

**Pengujian:** panel dilihat di browser oleh pemilik (daftar, ringkasan, dan penanda tampil benar). **Belum dicoba di browser:** pengalihan Owner dari `/organisasi`, tombol nonaktifkan/aktifkan, 403 untuk anggota organisasi nonaktif. Tabel di panel sempat tampak tanpa gaya di tangkapan layar; belum dikonfirmasi apakah normal setelah `npm run dev` dijalankan ulang. **Uji mutasi:** belum dilakukan (kandidat: `abort_unless` di `EnsureOwner`, dan `abort_if` di `OrganizationController::join`).

**Masalah terbuka baru:** bila Admin menambahkan email Owner sebagai kontak, akun Owner bisa tertaut ke organisasi saat verifikasi (celah kecil, dibiarkan untuk skala tugas kuliah).

**Langkah H — Tampilan (Tahap 5) dan penyempurnaan**
View prototipe halaman demi halaman (putuskan D7), navigasi/sidebar organisasi, terjemahkan teks Breeze, atur D8, perbarui `README.md`, hapus `welcome.blade.php`, syarat lingkungan untuk `OwnerSeeder`, uji di MariaDB, lampiran nota transaksi (D10), pertimbangkan Pull Request ke `main` (perhatikan GitHub Pages), tinjau masalah 28–31.

**Peta halaman prototipe → Laravel:** `organisasi.html`→`organizations.index` (ada) · `dashboard.html`→`organizations.dashboard` (dasar) · `transaksi.html`→`TransactionController` (ada) · `iuran.html`→`InvoiceController` (E1–E3 ada) · `anggota.html`→`MemberController` + `InvitationController` (ada) · `laporan.html`→`ReportController` · `pengaturan.html`→pengaturan organisasi + `CategoryController` (kategori ada) · `owner.html`→`Owner\OrganizationController`.


## Langkah H (sebagian) — layout, login, perbaikan pesan tagihan (12 Nov 2026)

**Selesai:**
- **Langkah 0:** pesan tagihan sendiri di `invoices/index.blade.php`. "Menunggu diverifikasi pengelola lain" kini hanya muncul bila `status === 'menunggu'`. Tagihan `belum` yang punya pengelola lain menampilkan "Pengelola lain bisa melunasi tagihan ini secara tunai." Dua tes lama diubah agar membuat tagihan `menunggu`, ditambah satu tes baru (`test_tagihan_sendiri_belum_diajukan_tidak_menampilkan_pesan_menunggu`). Tes: 218.
- **Layout organisasi (D7 diputuskan: Tailwind di Blade):** `layouts/app.blade.php` memakai sidebar bila ada `{organization}` di rute, dan `layouts/topbar.blade.php` bila tidak. Berkas baru: `layouts/sidebar.blade.php`, `layouts/topbar.blade.php`, `components/side-link.blade.php`. Warna dan font prototipe ada di `tailwind.config.js` (deep, brand, mint, soft, canvas, ink, mute, line, expense, income; font Plus Jakarta Sans). Sidebar menyembunyikan menu sesuai hak (`@can`), responsif (menu geser di layar kecil). Belum ada ikon dan badge.
- **Login (dua kolom):** `layouts/guest.blade.php` dua kolom (kolom kiri hanya di layar lebar), `auth/login.blade.php` berbahasa Indonesia. Kotak "Akun demo presentasi" (kata sandi demo) dan kartu "4 profil demo" **hanya tampil bila `APP_ENV=local`**. Tes baru: `LoginPageTest` (2 tes). Tes: 220.

**Aturan:** kata sandi demo tidak boleh tampil di luar `local`.

**Catatan:**
- `guest.blade.php` dipakai juga oleh Daftar, Lupa Kata Sandi, dan Reset, jadi ketiganya sudah berlayout dua kolom, tetapi isinya masih bahasa Inggris dan bergaya Breeze.
- Bila tampilan tampak tanpa gaya (sidebar jadi teks polos): hentikan `npm run dev`, jalankan `php artisan optimize:clear`, `npm run dev` lagi, lalu Ctrl+Shift+R. Penyebabnya server Vite belum menangkap `tailwind.config.js` baru.
- Dicoba di browser oleh pemilik: sidebar dan dashboard (isi masih gaya lama), login.
- **Belum ditinjau:** tampilan `/register`, `/forgot-password`, `/reset-password`.
