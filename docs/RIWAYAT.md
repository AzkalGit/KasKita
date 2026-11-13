> **ARSIP, bukan untuk ditempel utuh ke Claude.** Berisi aturan bisnis rinci, skema, daftar tes, keputusan, masalah, dan riwayat sampai **Langkah I-1 (13 Nov 2026)**.
> Yang berlaku untuk bekerja ada di `CLAUDE.md`. Tempel hanya bagian yang diminta, misalnya "bagian 3, Iuran".
> Terakhir dirapikan: 13 Nov 2026 · Tes terakhir diketahui: **236 lulus (1331 assertion)**.

## Daftar isi

1. Stack, repo, perintah
2. Skema database
3. Aturan bisnis per modul
4. Peran dan hak akses
5. Pengujian
6. Keputusan pemilik (D1-D18)
7. Masalah: terbuka dan selesai
8. Riwayat progres

---

## 1. Stack, repo, perintah

- **Stack:** Laravel 13.x, PHP ≥ 8.4.1, Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. Tes memakai SQLite di memori.
- **Lingkungan pemilik:** Linux Mint, PHP 8.4, MariaDB 10.11 (db `kaskita`, user `kaskita_user`), Node 22 (nvm). Periksa DB dengan `php artisan db:show`, `db:table <tabel>`, `tinker`.
- **Repo:** `https://github.com/AzkalGit/KasKita`, folder `~/Documents/Kuliah/sem3/SI/KasKita`. Branch `main` = prototipe statis (GitHub Pages), tidak disentuh. Kerja di `fitur/laravel-fondasi`. Prototipe lama di `prototipe-statis/`, catatan tahap di `docs/TAHAP.txt`.
- **Tautan verifikasi email** (`MAIL_MAILER=log`) dibaca dari `storage/logs/laravel.log`.
- **Disk file:** `FILESYSTEM_DISK=local` berakar `storage/app/private`. Bukti iuran memakai `Invoice::PROOF_DISK` (`local`) dan `Invoice::PROOF_DIR` (`proofs`). Disk `public` jangan dipakai untuk lampiran.

## 2. Skema database

`?` = boleh NULL.

| Tabel | Kolom | FK (hapus) | Unik |
| --- | --- | --- | --- |
| `users` | name, email, email_verified_at?, password, platform_role? (`owner`), remember_token | – | email |
| `organizations` | name, status (`aktif`\|`nonaktif`), opening_balance=0, default_dues=0, payment_info?, created_by? | created_by→users (set null) | – |
| `organization_members` | organization_id, user_id?, name, email?, phone?, role? (`admin`\|`editor`\|`anggota`), is_billable=1, is_active=1 | organization_id (cascade); user_id (set null) | (organization_id, email); (organization_id, user_id) |
| `categories` | organization_id, name, is_system=0 (true untuk "Iuran") | organization_id (cascade) | (organization_id, name) |
| `invoices` | member_id, period, amount (bigint), status (`belum`\|`menunggu`\|`lunas`), method? (`transfer`\|`tunai`), proof_path?, rejection_reason?, paid_at? | member_id→organization_members (**restrict**) | (member_id, period) |
| `transactions` | organization_id, category_id, invoice_id?, created_by?, type (`masuk`\|`keluar`), amount (bigint), tanggal, description, attachment_path? (belum dipakai), deleted_at? | organization_id (cascade); category_id (**restrict**); invoice_id (**restrict**); created_by (set null) | invoice_id; indeks (organization_id, tanggal) |
| `invitations` | organization_id, code, expires_at?, closed_at?, created_by? (hanya `created_at`) | organization_id (cascade); created_by (set null) | code |
| `invitation_uses` | invitation_id, user_id, used_at | cascade | – |
| `activity_logs` | organization_id?, user_id?, action, subject_type?, subject_id?, metadata JSON? (hanya `created_at`) | set null | – |
| `period_closures` | organization_id, period, closed_by?, closed_at | organization_id (cascade); closed_by (set null) | (organization_id, period) |

Catatan skema:
- Dua jenis "anggota": `user_id` terisi = punya akun; NULL = kontak yang ditagih. Peran hanya bermakna bila akun ada.
- `is_billable` ≠ `is_active`. `is_billable` tidak terkait peran: Admin/Editor baru punya tagihan setelah dicentang (Anggota → Ubah data) dan tagihan bulan itu dibuat ulang.
- `invoices` tidak punya `organization_id` (dicapai lewat `member`). `transactions.category_id` tidak dipaksa DB satu organisasi; divalidasi di `TransactionRequest`.
- Kolom `role`, `status`, `type`, `method` adalah string tanpa CHECK; validasi di aplikasi.
- `restrictOnDelete` pada `invoices.member_id`, `transactions.invoice_id`, `transactions.category_id` disengaja; hapus organisasi lewat `Organization::purge()`.
- Owner tidak punya tabel sendiri: `users.platform_role = 'owner'`.
- Migrasi lama diedit di tempat. Langkah B, D, E1, E2, E3, F, G, H, I tidak menambah migrasi.

## 3. Aturan bisnis per modul

### 3.1 Registrasi dan verifikasi email
- `User implements MustVerifyEmail`. Rute organisasi di grup `['auth','verified']`; profil hanya `auth`.
- `/dashboard` = pengalihan ke `/organisasi` (nama rute dipertahankan untuk Breeze). `/` mengalihkan ke `/login` (tamu) atau `/organisasi` (sudah masuk); `welcome.blade.php` sudah dihapus.
- Mendaftar tidak menautkan akun ke kontak. Penautan terjadi **setelah email terverifikasi** (`LinkPendingMemberships`): hanya baris `user_id` NULL, `is_active`, email sama, di organisasi yang belum diikuti akun itu; `role` NULL menjadi `anggota`.
- Akun dihapus: keanggotaan dinonaktifkan (`role` NULL, `is_active` false), tidak dihidupkan lagi. Admin tunggal tidak bisa menghapus akunnya.
- `platform_role` tidak mass-assignable (seeder pakai `forceCreate`).

### 3.2 Organisasi dan undangan (A, B)
- Membuat organisasi: pembuat jadi `admin`, `is_billable=false`; kategori "Iuran" (`is_system`) dibuat; satu kode undangan 7 hari (`Invitation::issue`).
- Gabung dengan kode: peran selalu `anggota`; kode dinormalkan; 5 kegagalan per akun dalam 15 menit memblokir (`RateLimiter`); pesan galat sama untuk semua sebab; ditolak bila sudah anggota atau nonaktif; tercatat di `invitation_uses`.
- `EnsureOrganizationMember`: anggota **aktif** (403) dan organisasi `aktif` (403).
- Dashboard: saldo = `opening_balance` + pemasukan − pengeluaran; lima transaksi terbaru.
- `Organization::purge()`: hapus transaksi (termasuk soft-deleted) → tagihan → organisasi dalam satu transaksi DB; belum ada UI.
- **Kelola undangan (Admin, `manageInvitations`):** masa berlaku 1/7/30 hari (`Invitation::DURATIONS`); banyak kode aktif boleh; `status()`: `ditutup` → `kedaluwarsa` → `aktif`; kedaluwarsa tidak bisa dibuka lagi; ganti kode = tutup lama + buat baru dalam satu `DB::transaction`; kode tidak pernah dihapus; 8 karakter tanpa `0/O/1/I`. Log: `invitation.created|closed|reopened|regenerated`.

### 3.3 Kelola anggota (C, E2) · C ditulis Pemandu, belum ditinjau sesi lain
- Hanya Admin (`manageMembers`), kecuali `leave` (anggota aktif non-Admin). Peran yang bisa diatur hanya Anggota ↔ Editor; Admin tidak bisa diberikan atau dicabut lewat form.
- Pembuat organisasi, Admin, dan diri sendiri terlindungi dari perubahan peran dan penonaktifan. Kontak tanpa akun tidak bisa diberi peran.
- Anggota dinonaktifkan, tidak dihapus; reaktivasi mengembalikan akun jadi Anggota. Email anggota berakun mengikuti akunnya.
- `is_billable` bisa diubah Admin lewat "Ubah data" (termasuk barisnya sendiri). E2: perubahan nilainya dicatat sebagai `member.billable_changed`.
- Log: `member.created|updated|billable_changed|role_changed|deactivated|reactivated|left|joined`.

### 3.4 Kategori dan transaksi (D, I-1) · belum ditinjau sesi lain
- **Kategori:** hanya Admin (`manageSettings`). Nama dipangkas, wajib, maks 100, unik per organisasi tanpa peka huruf besar/kecil. Kategori `is_system` tidak bisa diubah/dihapus. Kategori yang dipakai transaksi (termasuk soft-deleted) tidak bisa dihapus. Organisasi lain = 404. Log: `category.created|renamed|deleted`.
- **Transaksi:** semua anggota aktif melihat (`viewTransactions`); Admin/Editor mencatat dan mengubah (`manageTransactions`); hanya Admin menghapus (`deleteTransactions`, soft delete). `created_by` tidak berganti saat diubah.
- **Validasi (`TransactionRequest`):** `type` masuk|keluar; `category_id` milik organisasi dan bukan sistem; `amount` bulat 1 s.d. 100.000.000.000; `tanggal` `Y-m-d` min 2000-01-01; `description` maks 255.
- **Terkunci:** transaksi ber-`invoice_id` (`isLocked()`) tidak bisa diubah/dihapus.
- **Daftar:** filter `month` (`Y-m`), `type`, `q` (LIKE); ringkasan mengikuti filter; 15 per halaman. Log: `transaction.created|updated|deleted`.
- **Tampilan filter (I-1):** kartu berjudul "Filter"; `x-select-input` untuk Jenis; placeholder "Ketik sebagian keterangan"; Reset berupa tombol bergaris; judul daftar "Daftar transaksi · {Bulan Tahun} (N)" atau "Semua bulan". Nama bulan: `Carbon::locale('id')->translatedFormat('F Y')`.

### 3.5 Iuran (E1, E2, E3) · E3 ditulis sesi lain, ditinjau sebagian oleh Pemandu
- **Rute (`org.member`):** `invoices.index` (GET `/iuran`), `store` (POST), `pay_cash` (PATCH `/iuran/{invoice}/lunas-tunai`), `cancel` (`.../batalkan`), `approve` (`.../setujui`), `reject` (`.../tolak`), `submit` (POST `.../ajukan`), `withdraw` (PATCH `.../tarik`), `proof_download` (GET `.../bukti`), `proof_store` (POST), `proof_destroy` (DELETE). `{invoice}` dicari lewat `Invoice::inOrganization()` (organisasi lain = 404).
- **Hak:** semua anggota aktif membuka halaman (`viewInvoices`). Admin dan Editor (`manageInvoices`) melihat semua tagihan satu periode (bawaan terbaru; filter `period`), membuat tagihan, melunasi tunai, membatalkan pelunasan, menyetujui/menolak. Anggota hanya melihat tagihan miliknya, semua bulan.
- **Aturan tagihan sendiri:** `InvoiceController::isOwn()` (`member.user_id` = pelaku; kontak tanpa akun tidak pernah "milik sendiri"). `payCash`, `cancel`, `approve`, `reject` menolak tagihan sendiri dengan galat `invoice` (bukan 403), pesan `OWN_INVOICE_MESSAGE` / `OWN_VERIFY_MESSAGE`. View: tanda "(tagihanmu)", tanpa tombol verifikasi, keterangan sesuai ada/tidaknya pengelola lain aktif (`$hasOtherManager`). Pembatalan pelunasan tagihan sendiri hanya oleh pengelola lain.
- **Buat tagihan:** satu periode (`YYYY-MM`, 2000-an) dan jumlah untuk semua anggota aktif dan `is_billable`; yang sudah punya tagihan periode itu dilewati (jumlah lama tidak berubah). Setelah `is_billable` seseorang diaktifkan, tagihan bulan berjalan harus dibuat ulang. Log `invoice.generated`.
- **`Invoice::settle($id, $organization, $actor, ?$method, array $from)`:** dipakai bersama "Lunas tunai" (`METHOD_TUNAI`, dari `belum`/`menunggu`) dan "Setujui" (`method` null = pertahankan; dari `menunggu`). Dalam `DB::transaction` + `lockForUpdate`: tagihan jadi `lunas`, `paid_at` = sekarang, `rejection_reason` null; membuat transaksi `masuk` kategori Iuran (`firstOrCreate`) dengan `invoice_id`, `created_by` = pelaku, `tanggal` = hari ini, keterangan "Iuran {Bulan} {Tahun}" **tanpa nama anggota (D2)**. Mengembalikan `null` bila status asal tidak cocok. Log `invoice.paid_cash` / `invoice.approved`.
- **Batalkan pelunasan:** hanya untuk yang lunas; transaksi otomatis dihapus permanen (`forceDelete`, karena `invoice_id` unik); tagihan kembali `belum` (method/paid_at/rejection_reason/proof_path null; file bukti dihapus). Log `invoice.payment_cancelled`.
- **Pengajuan pembayaran (D11):** hanya pemilik tagihan (`SubmitPaymentRequest::authorize()`: organisasi lain 404; bukan pemilik 403). Dari `belum` → `menunggu`; `method` `transfer`|`tunai` wajib; `proof` opsional (`jpg,jpeg,png,pdf`, maks 2 MB), nama acak di `proofs/` disk privat; bila status ternyata bukan `belum`, file baru dibuang. Log `invoice.submitted` (metadata `has_proof`). Admin/Editor yang `is_billable` boleh mengajukan tagihannya sendiri.
- **Bukti:** selama `menunggu`, pemilik boleh `storeProof` (tambah/ganti, file lama dihapus; log `invoice.proof_updated`), `destroyProof` (log `invoice.proof_removed`), `withdraw` (kembali `belum`, bukti dihapus; log `invoice.withdrawn`). Di luar `menunggu` ditolak. Setelah `lunas`, bukti terkunci.
- **Tolak:** `RejectInvoiceRequest` (`manageInvoices`; alasan wajib, maks 255). Dari `menunggu` → `belum`, method/proof_path/paid_at null, alasan diisi, file bukti dihapus; pemilik boleh mengajukan ulang. Log `invoice.rejected`.
- **Unduh bukti:** `viewInvoices`, lalu hanya pemilik tagihan atau `manageInvoices` (selain itu 403); nama `bukti-iuran-{period}-{id}.{ext}`. Berkas hilang → dialihkan dengan galat "Berkas bukti tidak tersedia." Partial `invoices/_proof` menampilkan "Unduh bukti", "Berkas bukti tidak tersedia", atau "Tanpa bukti".
- **Model `Invoice`:** konstanta `STATUS_*`, `METHOD_*`, `PROOF_DISK`, `PROOF_DIR`; `scopeInOrganization`, `isPaid()`, `proofAvailable()`, `settle()`, `labelFor()`, `periodLabel()`, relasi `member()` dan `transaction()`.
- **Keterangan pesan (Langkah 0, H):** "Menunggu diverifikasi pengelola lain" hanya bila `status === 'menunggu'`; tagihan `belum` yang punya pengelola lain menampilkan "Pengelola lain bisa melunasi tagihan ini secara tunai."

### 3.6 Laporan (F1) · belum ditinjau sesi lain
- Rute `/laporan` dan `/laporan/unduh`; `ReportController` (`index`, `download`). Admin + Editor (`viewReports`); tunggakan hanya Admin (`viewArrears`; `arrears` = `null` untuk Editor).
- Filter bulan dan minggu (Senin-Minggu, dipotong batas bulan); saldo awal periode = `opening_balance` + transaksi sebelum periode; saldo akhir; rincian per kategori; daftar transaksi.
- **Tunggakan (D14):** semua tagihan `belum` + `menunggu` per hari ini.
- **CSV (D13):** BOM UTF-8, `fputcsv(..., ',', '"', '')`, nilai berawalan `= + - @` dinetralkan; log `report.exported`.

### 3.7 Tutup buku (F2) · belum ditinjau sesi lain
- Hanya Admin menutup dan membuka kembali (`closePeriods`). Admin organisasi lain tidak bisa menyentuh; penutupan satu organisasi tidak memengaruhi yang lain.
- Hanya bulan lampau yang bisa ditutup (D15); bulan berjalan/depan dan format salah ditolak. Menutup dua kali ditolak tanpa data ganda. Membuka bulan yang belum ditutup ditolak. Rute: `periods.close` (POST `/laporan/tutup`), `periods.reopen` (DELETE `/laporan/tutup/{period}`). Log tutup dan buka.
- Bulan tertutup menolak: membuat, mengubah, memindahkan (ke atau dari bulan itu), membuka form ubah, dan menghapus transaksi.
- Iuran: lunas tunai dan setujui ditolak bila bulan **hari ini** tertutup; batalkan pelunasan ditolak bila bulan **transaksi pembayarannya** tertutup. Anggota tetap boleh mengajukan pembayaran.
- Penolakan lewat `PeriodClosedException` dengan pesan jelas. Halaman Laporan menampilkan status tutup; tombol Tutup/Buka hanya untuk Admin.

### 3.8 Panel Owner (G) · belum ditinjau sesi lain
- File: `EnsureOwner` (alias `owner`, 403 bila bukan `isOwner()`), `OwnerOrganizationController` (`index`, `deactivate`, `activate`), `owner/organizations/index.blade.php`, `OwnerPanelTest` (10), `OwnerSeparationTest` (4).
- Rute: `GET /owner/organisasi/daftar`, `PATCH /owner/organisasi/{organization}/nonaktifkan|aktifkan`; middleware `auth`, `verified`, `owner`; di luar `org.member`.
- Panel hanya menampilkan metadata: nama, tanggal dibuat, jumlah anggota aktif bersakun, status, penanda "Tanpa Admin", ringkasan jumlah organisasi dan akun (tanpa akun Owner). Query hanya memilih `id, name, status, created_at`; saldo, transaksi, tagihan, `payment_info`, nama/email anggota tidak dibaca (ada tesnya).
- Nonaktifkan/aktifkan dilog (`organization.deactivated|activated`, metadata from/to); status sama ditolak tanpa log. Efek: anggota organisasi nonaktif mendapat 403; data tidak dihapus; kode undangan ditolak.
- **D17:** Owner yang membuka `/organisasi` dialihkan ke panel; membuat organisasi dan bergabung dengan kode ditolak 403.

### 3.9 Tampilan (H, I-1)
- **D7:** Tailwind di Blade. Token di `tailwind.config.js`: `deep, brand, mint, soft, canvas, ink, mute, line, expense, income`; font Plus Jakarta Sans.
- **Layout:** `layouts/app` memakai sidebar bila ada `{organization}`, selain itu `layouts/topbar`. Berkas: `sidebar`, `topbar`, `components/side-link`. Sidebar menyembunyikan menu sesuai hak (`@can`); di bawah 1024px ada bar atas + hamburger (di kiri). Belum ada ikon sidebar. `layouts/navigation.blade.php` sudah dihapus.
- **Login:** `guest.blade.php` dua kolom; `auth/login.blade.php` berbahasa Indonesia. Aturan: kata sandi/akun demo tidak boleh tampil di luar `local` (belakangan, di I-0, dihapus sepenuhnya dari UI; lihat D18). Dipakai juga oleh Daftar, Lupa/Reset kata sandi.
- **Dashboard:** kartu ringkasan, grafik batang CSS murni, transaksi terakhir tanpa nama (D2), banner bukti menunggu, badge angka Iuran di sidebar (hanya pengelola). Ada `DashboardTest`.
- **Komponen:** `primary-button`, `secondary-button`, `danger-button`, `text-input`, `select-input`, `input-label`, `input-error`, `badge` (green/yellow/red/gray), `side-link`. Paginasi: `vendor/pagination/tailwind.blade.php`.
- **Halaman yang sudah diseragamkan:** Transaksi, Iuran, Anggota, Laporan (+Tutup buku), Kategori, Undangan, Organisasi, Panel Owner, Profil, Daftar, Lupa/Reset, Verifikasi email, Konfirmasi kata sandi. Halaman tanpa sidebar membawa wadah sendiri (`mx-auto max-w-* px-4 py-8`).
- **Sengaja belum ada di Laravel:** filter kategori dan rentang tanggal di Transaksi, ekspor CSV di Transaksi, lampiran nota (D10).
- **Tampak tanpa gaya?** Hentikan `npm run dev`, `php artisan optimize:clear`, `npm run dev`, Ctrl+Shift+R.

### 3.10 Data demo (I-0)
- `migrate:fresh --seed` membuat Owner + 3 akun utama + 4 organisasi (HMMI, HMMI Divisi Kewirausahaan, BEM, Komunitas Freelancer Digital Nusantara). `php artisan kaskita:isi-demo` (hanya `local`/`testing`) menambah 24 akun, tagihan, dan transaksi. Daftar akun dan kata sandi **tidak** ditulis di repo; pemilik menyimpannya di luar repo (D18).
- Seeder lama (`owner@kaskita.demo`, BEM Fakultas Teknologi, kode `BEM26KAS`) kini hanya fixture tes (`TestOrganizationSeeder`).
- *Entri ini disusun dari ringkasan `CLAUDE.md`, bukan dari catatan langkah aslinya.*

## 4. Peran dan hak akses

Semua = keanggotaan aktif. `OrganizationPolicyTest` memeriksa seluruh matriks.

| Kemampuan | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: |
| `viewDashboard`, `viewTransactions`, `viewInvoices` (Anggota hanya miliknya) | ya | ya | ya |
| `viewReports` | ya | ya | – |
| `viewArrears` | ya | – | – |
| `manageTransactions`, `manageInvoices` (kecuali tagihan sendiri) | ya | ya | – |
| `deleteTransactions`, `manageMembers`, `manageInvitations`, `manageSettings`, `closePeriods` | ya | – | – |

- **Aturan peran:** pembuat organisasi tidak bisa diturunkan/dikeluarkan; tak seorang pun mengubah perannya sendiri; kode undangan hanya memberi Anggota. "Minimal satu Admin" terjamin tidak langsung (Admin tidak bisa diturunkan/keluar); harus dirancang ulang bila promosi Admin dibuat (D5).
- **Aturan Iuran:** tak seorang pun melunasi, memverifikasi, atau membatalkan pelunasan tagihannya sendiri. Pemilik tagihan tetap boleh mengajukan dan mengurus bukti tagihannya. Organisasi tanpa pengelola kedua membuat tagihan pengelola menggantung (pesan jelas di halaman Iuran). Kolusi dua pengelola tidak bisa dicegah kode; yang tersedia hanya jejak log dan transaksi yang terlihat semua anggota.
- **Pengajuan pembayaran dan bukti** bukan kemampuan Policy; itu hak pemilik tagihan (`SubmitPaymentRequest`, `isOwn`).
- **Owner platform:** hanya `/owner/*`; tidak pernah menyentuh `transactions`, `invoices`, atau lampiran (dengan tes). Pemegang server/database tetap bisa membaca data lewat alat database; yang dijamin hanya tidak adanya fitur mengintip, kata sandi ter-hash, dan panel berisi metadata.

## 5. Pengujian

- **Terakhir:** 236 lulus (1331 assertion), SQLite di memori. Aplikasi memakai MariaDB; kolasi dan FK belum diuji lewat tes. Penyimpanan file di tes memakai `Storage::fake('local')`; disk sungguhan belum diuji.
- **Berkas tes (satu per modul):** Auth Breeze, `ExampleTest` (redirect `/`), `OrganizationTest`, `MembershipLinkingTest`, `AccountDeletionTest`, `OrganizationPurgeTest`, `RoutingTest`, `ProfileTest`, `OrganizationPolicyTest`, `InvitationManagementTest`, `MemberManagementTest`, `MemberBillableTest`, `CategoryManagementTest`, `TransactionManagementTest`, `InvoiceManagementTest`, `InvoicePaymentTest`, `ReportTest`, `PeriodClosureTest`, `OwnerPanelTest`, `OwnerSeparationTest`, `DashboardTest`, `LoginPageTest`, `IsiDemoCommandTest`.
- **Sudah dicoba di browser (pemilik):** login, daftar, Dashboard, Transaksi (versi sebelum I-1), hamburger di layar sempit, daftar Panel Owner, sebagian E2 (Anggota yang ditagih tidak bisa melunasi tagihannya sendiri).
- **Belum dicoba di browser:** tampilan filter Transaksi baru (I-1); login 4 akun utama dan peran per organisasi (Azkal masuk panel Owner); hamburger terbuka; `/` → `/login`; Iuran (termasuk seluruh alur E3), Anggota, Laporan (+Tutup buku), Kategori, Undangan (tombol Salin hanya jalan di `localhost`/`127.0.0.1`/HTTPS), Organisasi, Profil, halaman auth baru, paginasi; CSV di Excel/LibreOffice.
- **Uji mutasi:** hanya E1 satu kali (Gate `manageInvoices` di `payCash`, sudah dipulihkan). Belum: D, E2, E3, F1, F2, G. Kandidat: `isOwn` di `approve`/`reject`, `abort_unless` di `withdraw`/`destroyProof`, otorisasi dan isolasi F2, `EnsureOwner`, `abort_if` di `OrganizationController::join`.

## 6. Keputusan pemilik

| # | Soal | Keputusan / keadaan |
| --- | --- | --- |
| D1 | Hak Editor | **Diterapkan.** Transaksi, laporan tanpa tunggakan, Iuran (kecuali tagihan sendiri). Pengaturan, anggota, undangan, kategori, hapus transaksi tetap Admin. |
| D2 | Anggota melihat dashboard | **Boleh, hanya lihat** (dashboard dan transaksi). Keterangan transaksi iuran tanpa nama; tunggakan hanya Admin. |
| D3 | Admin/Editor ikut ditagih | **Diterapkan.** Diset `is_billable=true` (Anggota → Ubah data); tidak memverifikasi tagihan sendiri. Dosen pengawas = Anggota `is_billable=false`. |
| D4 | Kontak berbeda email akun terverifikasi | Tidak ditautkan otomatis; usulan: undangan yang harus diterima. |
| D5 | Serah-terima Admin, promosi Admin, hapus organisasi | Belum ada. Admin belum bisa keluar; jaminan "minimal satu Admin" perlu dirancang ulang. |
| D6 | Nama organisasi kembar | Boleh. |
| D7 | CSS | **Tailwind di Blade** (diputuskan di Langkah H). |
| D8 | Locale `id` / timezone `Asia/Jakarta` | Belum diubah (sekarang `en` / UTC). |
| D9 | Kode undangan | 1/7/30 hari; kode awal 7 hari; banyak kode aktif; ditutup, tidak dihapus. |
| D10 | Lampiran | Bukti bayar iuran **selesai (E3)**; nota transaksi manual menyusul. |
| D11 | Bukti bayar | **Diterapkan.** "Saya sudah bayar" (transfer/tunai); bukti opsional; boleh ditambah/diganti/dihapus/ditarik selama `menunggu`; verifikator setuju atau tolak dengan alasan; terkunci setelah `lunas`; disk privat, jpg/png/pdf maks 2 MB, nama acak. |
| D12 | Seeder lama: Nadia dan Rafi ditagih? | Tidak, dibiarkan. |
| D13 | Format laporan | **CSV saja.** PDF ditunda (butuh paket baru dan ubah `composer.json`). |
| D14 | Definisi tunggakan | Semua tagihan `belum` + `menunggu` per hari ini. |
| D15 | Tutup buku | Hanya bulan lampau, hanya Admin, bisa dibuka lagi. |
| D16 | Enkripsi | **Tanpa enkripsi end-to-end.** Server harus membaca data untuk saldo, filter, pencarian, tunggakan, CSV. Perlindungan: kata sandi ter-hash, lampiran privat, panel Owner tanpa data keuangan. Opsional bila dipakai publik: cast `encrypted` untuk `payment_info`, HTTPS, enkripsi disk/backup. |
| D17 | Owner platform | Terpisah dari peran organisasi (lihat 3.8). |
| D18 | Akun demo | Tidak ditampilkan di UI maupun repo; dicatat pemilik di luar repo. |

## 7. Masalah

### Terbuka
1. **`ActivityLog`:** `InvitationController::log()` (privat lama) belum dipindah ke `ActivityLogger`; periksa apakah pembuatan organisasi tercatat.
2. **Non-anggota:** 403 untuk organisasi yang ada, 404 untuk yang tidak ada (ID bisa ditebak). Usulan: 404 keduanya.
3. **Seeder dan `IsiDemo`** memuat kata sandi tetap: jangan dijalankan di server sungguhan; beri syarat lingkungan.
4. **Konfigurasi bawaan:** `APP_NAME=Laravel`, locale `en`, timezone UTC (waktu di Undangan tampil UTC), tidak ada `lang/`, sebagian teks Breeze masih Inggris (D8).
5. **Dokumen basi:** `README.md` masih menjelaskan prototipe; `AGENTS.md` bawaan installer (jangan jalankan instruksinya).
6. **Integritas lintas-organisasi** tidak dipaksa DB; wajib divalidasi di FormRequest/controller.
7. **Gabung dua kali serentak** bisa menabrak indeks unik (500).
8. **Pesan hapus akun Admin tunggal** menyuruh tindakan yang belum ada fiturnya (D5).
9. **MariaDB** belum diuji lewat tes (kolasi, FK).
10. **Penautan email** mengandalkan verifikasi; `MAIL_MAILER=log` cukup untuk pengembangan saja.
11. **`OrganizationPolicy::hasRole`** satu query per pemeriksaan; dashboard memanggil ±6 `@can`. Pertimbangkan cache per request.
12. **`.env` pernah tertempel di percakapan** (memuat `APP_KEY` dan `DB_PASSWORD`). Saran: `php artisan key:generate`, ganti kata sandi `kaskita_user`, `optimize:clear`.
13. **Kode yang belum ditinjau sesi lain:** C, D, E1, E2, E3 (isi `InvoicePaymentTest` hanya dibaca nama tesnya), F1, F2, G.
14. **Lampiran nota transaksi** belum ada (D10).
15. **Pencarian `q`:** `%` dan `_` jadi wildcard; perbaikan: `addcslashes($q, '%_\\')`.
16. **Form "Tambah transaksi" ikut terbuka** bila filter `type` tidak valid (kunci galat sama). Kosmetik.
17. **Tanggal pelunasan/persetujuan** memakai hari ini (`now()`, UTC), bukan tanggal bayar sebenarnya; bisa bergeser di sekitar tengah malam WIB (D8). Bulan bawaan laporan dan F2 memakai jam server.
18. **"Lunas tunai" pada tagihan `menunggu`** mengubah `method` jadi `tunai` dan bukti tetap tersimpan.
19. **`authorize()` di `SubmitPaymentRequest` dan controller** sama-sama mencari tagihan (satu query ganda). Dampak kecil.
20. **File bukti yatim:** kegagalan mendadak di antara simpan file dan transaksi DB bisa meninggalkan file di `storage/app/private/proofs`. Pertimbangkan perintah pembersih.
21. **Laporan:** daftar transaksi tidak dipaginasi; CSV berkoma bisa terbuka dalam satu kolom di Excel berlokal Indonesia.
22. **Celah kecil:** bila Admin menambahkan email Owner sebagai kontak, akun Owner bisa tertaut saat verifikasi (dibiarkan).
23. **Tampilan:** sidebar belum punya ikon; input Login bergaya manual (`rounded-[10px]`, seharusnya `x-text-input`); `<select>` lain selain filter Transaksi belum diperiksa.

### Selesai
- Aturan tagihan sendiri dan hak Editor di Iuran (E2).
- Pengajuan bayar dan bukti (E3).
- `is_billable` dari halaman Anggota (ada; E2 menambah log).
- Organisasi tanpa pengelola kedua (pesan di E2).
- Seeder lama: Nadia dan Rafi tidak ditagih (diputuskan dibiarkan, D12); seeder tertempel dua kali (ternyata salah tempel).
- Komentar basi di `routes/web.php` (diperbarui di E3).
- Keterangan menyesatkan di view Iuran (diperbaiki di Langkah 0, H).
- Tampilan Transaksi melebar dan tanpa gaya (penyebab: Vite belum menangkap `tailwind.config.js`; lihat 3.9).
- Filter Transaksi kurang jelas (I-1).
- Akun demo tampil di halaman login (D18, I-0).

## 8. Riwayat progres

| Tanggal | Langkah | Ringkasan | Tes |
| --- | --- | --- | --- |
| sebelum 3 Nov | Dasar | Prototipe statis; Tahap 1-2 (tabel, model); seeder; Breeze; `OrganizationController`, `EnsureOrganizationMember`, dashboard. | 1 dari 25 |
| 3 Nov | Audit fondasi | `MustVerifyEmail` + penautan setelah verifikasi; email huruf kecil; hapus akun menonaktifkan keanggotaan; `Organization::purge()`; `/organisasi` lengkap; rute ganda dibersihkan. | 62 |
| 5 Nov | A | `OrganizationPolicy`, `hasRole()`, gerbang dashboard; D1, D2. | 66 |
| 6 Nov | B | Kelola undangan (controller, request, view, 15 tes); lima uji mutasi. | 81 |
| 7 Nov | C | Kelola anggota (Pemandu): `ActivityLogger`, `MemberController`, 19 tes; tombol Keluar. | 100 |
| 11 Nov | D | Kategori dan transaksi + 25 tes; Policy `viewTransactions`; `dashboard.blade.php` bawaan dihapus. | 125 (797) |
| 11 Nov | E1 | `InvoiceController`, `StoreInvoiceRequest`, view, 16 tes; Policy `viewInvoices`; satu uji mutasi berhasil. | 141 (899) |
| 11 Nov | E2 | `manageInvoices` = Admin + Editor; `isOwn()`; `$hasOtherManager`; `member.billable_changed`; `MemberBillableTest`. Dikomit `d1f22e8`; D12. | 150 (955) |
| 12 Nov | E3 | Kode ditulis sesi lain, ditemukan dan ditinjau sebagian oleh Pemandu. Ajukan, bukti, setujui, tolak, tarik, unduh; `InvoicePaymentTest` (18). Tanpa migrasi. | 168 (1047) |
| 12 Nov | D13 | Keputusan: laporan CSV saja; Langkah F dibagi F1 dan F2. | 168 (1047) |
| 12 Nov | F1 | `ReportController`, view, `ReportTest` (16), CSV; D14. | 184 (1132) |
| 12 Nov | F2 | Tutup buku: `PeriodClosureController`, `PeriodClosedException`, `PeriodClosureTest` (19); D15. | 203 (1207) |
| 12 Nov | G | Panel Owner: `EnsureOwner`, `OwnerOrganizationController`, 14 tes; D16, D17. Kejadian: `RoutingTest` gagal karena `Route::get('/')` di grup berawalan; diperbaiki dengan path `/daftar`. | 217 (1260) |
| 12 Nov | H (awal) | Langkah 0 (pesan tagihan sendiri, +1 tes); layout sidebar/topbar (D7); login dua kolom + `LoginPageTest`. | 220 |
| 13 Nov | H (lanjutan) | Penyeragaman tampilan: dashboard, komponen, paginasi, semua halaman. Hanya tampilan. | 229 |
| 13 Nov | I-0 | Data demo (`kaskita:isi-demo`, 4 akun utama, 4 organisasi), redirect `/`, login bersih (D18). Disusun dari ringkasan `CLAUDE.md`. | 235 |
| 13 Nov | I-1 | Filter Transaksi dirapikan (lihat 3.4) + `test_kartu_filter_berjudul_dan_judul_daftar_menyebut_bulan`. **Insiden:** percobaan pertama memakai blok `@php` (regex, `match`) → `ParseError`, halaman 500, 6 tes gagal; dipulihkan dengan `git checkout`, penyebab pasti tidak dipastikan; versi final memakai satu ekspresi `{{ }}`. Pelajaran: view yang belum dijalankan = belum teruji. | **236 (1331)** |