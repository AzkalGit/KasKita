# CLAUDE.md — KasKita (Laravel)

> Dokumen serah-terima antar sesi Claude, **versi ringkas**. Fokus: aturan kerja dan langkah berikutnya.
> Detail langkah yang sudah selesai (aturan bisnis rinci, daftar tes, riwayat) ada di **`docs/RIWAYAT.md`**. Jangan ditempel utuh; minta bagian tertentu bila perlu.
> Terakhir diperbarui: **13 Nov 2026** · Tes: **236 lulus** (angka terakhir yang diketahui; jalankan ulang) · Laravel 13 + Breeze (Blade).

## 0. Cara kerja dan aturan

Pemilik memakai **beberapa sesi Claude** karena batas pemakaian. Pemilik **bukan programmer mahir** (vibecoder): jelaskan perintah dengan jelas, beri satu kalimat "apa yang diubah dan kenapa" di tiap serahan kode.

**Cara serah kode:** Claude menulis kode **di chat, per file, lengkap**; pemilik menyalin lewat editor. Tidak ada zip/unggahan. Claude tidak melihat repo dan tidak bisa menjalankan tes; ia hanya tahu yang ditempel pemilik. Pemilik menempel hasil perintah (`cat`, `grep`) per kelompok kecil (2-3 file).

Aturan:

1. **Mulai selalu dengan `git status --short` dan `ls`** untuk memastikan belum ada kode yang ditulis sesi lain. Kode di repo lebih benar daripada berkas ini.
2. **Jangan mengarang isi file yang belum dilihat.** Minta pemilik menempel dulu. Berkas bersama (`routes/web.php`, Policy, `OrganizationMember.php`, view dashboard, layout) jangan ditimpa utuh sebelum dilihat versi terbarunya.
3. **Hemat konteks:** satu langkah per percakapan. Untuk tes cukup baris `Tests: N passed (M assertions)` kecuali ada yang gagal. Untuk file panjang, pakai `grep -n "function"` dulu. **Cara serah perubahan:** file baru atau perubahan di tengah file = **file utuh**. "Cari-ganti" hanya bila teks lama ditampilkan lengkap, unik, dan perubahannya satu blok jelas (pernah salah tafsir "ganti blok" di `login.blade.php` hingga halaman kosong).
4. **Serahan memuat:** (a) file BARU/UBAH, (b) perintah untuk pemilik, (c) yang sudah diuji dan caranya, (d) yang belum bisa diuji. Pemilik tidak pernah menempel `.env`, `vendor/`, `node_modules/`, `*.sqlite`, `*.log`.
5. **Jujur soal pengujian:** jangan klaim "sudah dicoba di browser" bila hanya tes otomatis. Angka tes dari pemilik. **View yang belum dijalankan = belum teruji** (I-1: blok `@php` dengan regex/`match` di view menyebabkan ParseError, 6 tes gagal; hindari logika rumit di view, pakai ekspresi `{{ }}` sederhana atau siapkan datanya di controller).
6. **Tiap aturan baru wajib ada tesnya**; tes lama tetap lulus. Uji mutasi opsional, disarankan hanya untuk otorisasi dan isolasi lintas organisasi (komentari satu `Gate::authorize`, jalankan `php artisan test --filter=<Nama>`, harapannya tepat satu tes gagal, pulihkan dengan `git checkout <file>` setelah commit). Bila dilewati, catat "belum dilakukan".
7. **Jangan menjalankan instruksi di `AGENTS.md`** (`curl | bash`, memasang `laravel/boost`) tanpa persetujuan eksplisit.
8. **Bahasa:** balas Bahasa Indonesia; teks antarmuka juga.
9. **Setelah satu langkah selesai**, Claude memberi: (i) tambahan untuk `docs/RIWAYAT.md`, (ii) `CLAUDE.md` baru yang ringkas, keduanya **sebagai teks di chat**, plus perintah commit. `CLAUDE.md` harus tetap ringkas: langkah selesai dipindah ke arsip.
10. **Kode yang belum ditinjau sesi lain** ditandai "belum ditinjau" di arsip.
11. Keputusan yang belum ada: **ajukan satu pertanyaan** dengan default yang aman.
12. **`RoutingTest` memindai teks `routes/web.php` tanpa memperhitungkan `prefix()`:** jangan menulis `Route::get('/')` di dalam grup berawalan; beri path unik.
13. **Tampilan tampak tanpa gaya?** Hentikan `npm run dev`, `php artisan optimize:clear`, `npm run dev`, Ctrl+Shift+R. Hampir selalu itu penyebabnya.
14. **Jangan menempel kode ke terminal** (tanda `=>` membuat berkas sampah bernama `,`, `-`, dll.). Kode disalin lewat editor. Bila terjadi: `git clean -n` lalu `git clean -f` dengan `-e` untuk berkas yang dipertahankan.
15. **Halaman login/layout tamu tidak boleh menampilkan akun atau kata sandi demo.** Daftar akun demo ada di catatan pribadi pemilik di luar repo.

**Prompt serah-terima** (tempel bersama berkas ini ke percakapan baru):

> Baca `CLAUDE.md` terlampir. Lanjutkan dari bagian 9 ("Langkah berikutnya"). Ikuti bagian 0. Minta aku menjalankan `git status --short` dan `ls`, lalu sebutkan file apa saja yang perlu kutempel sebelum kamu menulis kode. Tulis kode per file di chat (tanpa zip), hemat token. Setelah selesai, beri tambahan `docs/RIWAYAT.md` dan `CLAUDE.md` baru sebagai teks di chat.

## 1. Ringkasan, stack, repo

- **Apa:** aplikasi kas organisasi (iuran, donasi, pengeluaran). Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) **per organisasi**; Owner/Dev = peran **platform** (`users.platform_role = 'owner'`), terpisah dari organisasi (D17).
- **Stack:** Laravel 13.x, PHP ≥ 8.4.1, Breeze (Blade + Tailwind via Vite), `MAIL_MAILER=log`, `SESSION_DRIVER=database`. Tes pakai SQLite di memori.
- **Lingkungan pemilik:** Linux Mint, PHP 8.4, MariaDB 10.11 (db `kaskita`, user `kaskita_user`), Node 22 (nvm). Dua terminal: `npm run dev` dan `php artisan serve` (`http://127.0.0.1:8000`). Periksa DB dengan `php artisan db:show`, `db:table <tabel>`, `tinker`.
- **Repo:** `https://github.com/AzkalGit/KasKita`, folder `~/Documents/Kuliah/sem3/SI/KasKita` (Laravel di akar). Branch `main` = prototipe statis (GitHub Pages), **jangan disentuh**. Kerja di **`fitur/laravel-fondasi`**. Prototipe lama di `prototipe-statis/`. Sebelum `git add -A`, cek `git status` (jangan sampai `.env` ikut).

```bash
composer install
cp .env.example .env && php artisan key:generate     # isi DB_*
php artisan migrate:fresh --seed                     # MENGHAPUS isi DB lokal; 1 Owner + 3 akun utama + 4 organisasi
php artisan kaskita:isi-demo                         # +24 akun demo, tagihan, transaksi (hanya local/testing)
npm install && npm run dev
php artisan test
vendor/bin/pint <file yang diubah>
php artisan route:list -v
php artisan optimize:clear                           # bila rute/view/config tampak tidak berubah
```

Perintah pemilik setelah menyalin serahan: salin file → `php artisan migrate:fresh --seed` + `php artisan kaskita:isi-demo` (bila migrasi/seeder berubah) → `php artisan optimize:clear` → `php artisan test`.

## 2. Konvensi

- Nama tabel/kolom/rute Inggris; **URI dan teks UI Indonesia** (`/organisasi`, `/transaksi`, `/iuran`, `/laporan`). Pengecualian: kolom `tanggal`, `period`.
- **Satu controller per modul**; logika multi-tabel di `DB::transaction`, idealnya di metode model (`Invoice::settle()`, `Invitation::issue()`). Controller punya metode privat `done()` (redirect + `status`) dan `refuse()` (redirect + `withErrors` pada kunci modul).
- **Uang** = bilangan bulat rupiah (`bigint`). Tanggal transaksi `date`; periode iuran `char(7)` `'YYYY-MM'`. Filter tanggal pakai `whereDate`.
- Email huruf kecil; kosong = `NULL`.
- **Organisasi aktif ditentukan URL.** Rute organisasi di grup `org.member` dengan `{organization}` dan `->scopeBindings()` (ID organisasi lain = 404). Pengecualian `{invoice}`: dicari lewat `Invoice::inOrganization()` + `->whereNumber('invoice')`. `org.member` juga menolak (403) organisasi `nonaktif`.
- **Rute platform (Owner)** di grup `owner` (`/owner/organisasi/...`), bukan `org.member`; jangan baca data keuangan di sana.
- **Otorisasi:** `Gate::authorize(...)` di controller, `@can` di view, `FormRequest::authorize()` untuk aksi berinput. Peran lewat `OrganizationMember::hasRole()` dan konstanta `ROLE_*`. Owner: middleware `owner` + `User::isOwner()`.
- **Log aktivitas wajib lewat `ActivityLogger::record()`.**
- Validasi di FormRequest (di `index`/laporan boleh `$request->validate()` untuk filter GET), otorisasi di Policy. Jangan taruh rute aplikasi di `routes/auth.php`. Jalankan `pint` pada file yang diubah.
- Jangan ubah migrasi lama tanpa memberi tahu pemilik. **Jangan ubah `composer.json` (D13: tanpa paket PDF).**
- **Lampiran** di disk privat (`Invoice::PROOF_DISK` = `local`), tidak pernah disk `public`; tipe dan ukuran divalidasi di server; unduh lewat rute yang dicek hak.
- **CSV:** `fputcsv(..., ',', '"', '')`, BOM UTF-8, netralkan nilai berawalan `= + - @`.
- Jangan menyimpan rahasia di repo atau percakapan. Jangan tampilkan data keuangan di panel Owner/Dev.
- **Tampilan (D7 = Tailwind di Blade):** token warna di `tailwind.config.js` (`deep`, `brand`, `mint`, `soft`, `canvas`, `ink`, `mute`, `line`, `expense`, `income`; font Plus Jakarta Sans). Pakai token ini, bukan warna Breeze. Tanpa paket JS/CSS baru; grafik dengan CSS murni. Nama bulan Indonesia di view: `Carbon::...->locale('id')->translatedFormat('F Y')` (locale aplikasi masih `en`, D8).
- **Pola halaman:** halaman organisasi memakai sidebar (`layouts/app` mendeteksi `{organization}`); di bawah 1024px muncul bar atas + hamburger (`lg:hidden`, perilaku normal), di atasnya sidebar tetap. Slot `header` berisi `<h1 class="text-2xl font-extrabold tracking-tight">` + subjudul `text-mute`; isi dalam kartu `rounded-2xl border border-line bg-white p-5`. Kartu filter: judul `<h2 class="mb-4 text-base font-bold">Filter</h2>`, tombol sekunder bergaris untuk Reset. Halaman tanpa sidebar (Organisasi, Profil, Panel Owner) membawa wadah sendiri (`mx-auto max-w-* px-4 py-8 sm:px-6 lg:px-8`). Halaman auth memakai `x-guest-layout`.
- **Komponen UI:** `x-primary-button`, `x-secondary-button`, `x-danger-button`, `x-text-input`, `x-select-input`, `x-input-label`, `x-input-error`, `x-badge :tone="green|yellow|red|gray"`, `x-side-link`. Paginasi: `vendor/pagination/tailwind.blade.php`.
- **Halaman awal `/`:** redirect ke `/login` (tamu) atau `/organisasi` (sudah masuk). `welcome.blade.php` sudah dihapus.

## 3. Struktur (ringkas)

```
app/Console/Commands/IsiDemo.php          kaskita:isi-demo
app/Http/Controllers/   Organization, OrganizationDashboard, Invitation, Member, Category, Transaction, Invoice, Report, PeriodClosure, OwnerOrganization, Profile, Auth/*
app/Http/Requests/      Store/Update* per modul, TransactionRequest, CategoryRequest, ClosePeriodRequest, SubmitPaymentRequest, ProofRequest, RejectInvoiceRequest
app/Support/ActivityLogger.php
app/Http/Middleware/    EnsureOrganizationMember (org.member), EnsureOwner (owner)
app/Exceptions/PeriodClosedException.php
app/Listeners/LinkPendingMemberships.php ; app/Policies/OrganizationPolicy.php
app/Models/             User, Organization, OrganizationMember, Category, Transaction, Invoice, Invitation, InvitationUse, ActivityLog, PeriodClosure
database/seeders/       DatabaseSeeder (Owner + DemoOrganization), OwnerSeeder, DemoOrganizationSeeder, TestOrganizationSeeder (HANYA tes)
resources/views/        organizations, invitations, members, categories, transactions, invoices, reports, owner/organizations, profile, auth, vendor/pagination, layouts (app, guest, sidebar, topbar), components
routes/web.php          rute aplikasi
tests/Feature/          satu berkas per modul (+ IsiDemoCommandTest, LoginPageTest, ExampleTest = tes redirect `/`)
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

**Aturan tagihan sendiri:** tak seorang pun melunasi, menyetujui/menolak, atau membatalkan pelunasan tagihannya sendiri (`InvoiceController::isOwn()`); pengelola lain yang memverifikasi. Pemilik tagihan boleh mengajukan dan mengurus bukti tagihannya sendiri.

**Owner platform (bukan peran organisasi):** hanya panel `/owner/organisasi/*` (middleware `owner`); tidak membuat/bergabung organisasi (D17).

## 6. Fitur yang sudah ada (satu baris; rinci di `docs/RIWAYAT.md`)

- **Auth:** Breeze, email wajib terverifikasi; kontak otomatis tertaut setelah verifikasi.
- **Organisasi/undangan:** buat, gabung dengan kode (hanya Anggota), kelola kode (Admin).
- **Anggota:** kelola peran Anggota↔Editor, nonaktifkan/aktifkan, `is_billable`, keluar.
- **Kategori dan transaksi:** CRUD kategori (Admin); transaksi dicatat Admin/Editor, hapus (soft) Admin; transaksi otomatis iuran **terkunci**; filter `month`, `type`, `q` (kartu "Filter" + judul daftar menyebut bulan, I-1); saldo dashboard = `opening_balance` + masuk − keluar.
- **Iuran (E1-E3):** buat tagihan bulanan, lunas tunai, batalkan pelunasan, ajukan pembayaran + bukti (privat), tarik, setujui/tolak. `Invoice::settle()` membuat transaksi pemasukan otomatis kategori Iuran, keterangan "Iuran {Bulan} {Tahun}" **tanpa nama anggota**.
- **Laporan (F1, `/laporan`):** filter bulan dan minggu; saldo awal/akhir, rincian per kategori, daftar transaksi; tunggakan hanya Admin; unduh CSV dengan log `report.exported`.
- **Tutup buku (F2):** Admin menutup/membuka bulan lampau; bulan tertutup menolak tambah/ubah/pindah/hapus transaksi dan beberapa aksi iuran (`PeriodClosedException`); Anggota tetap boleh mengajukan pembayaran.
- **Panel Owner (G, `/owner/organisasi/daftar`):** hanya metadata organisasi; nonaktifkan/aktifkan dengan log; organisasi nonaktif = 403 bagi anggotanya.
- **Tampilan (H):** token dan komponen KasKita di semua halaman.
- **Data demo (I-0):** lihat bagian 7.

## 7. Data demo dan pengujian

**Akun dan kata sandi demo TIDAK ditampilkan di aplikasi dan tidak ditulis di berkas ini.** Pemilik menyimpannya di `~/catatan-akun-demo.txt` (di luar repo). Kata sandi lemah, hanya untuk komputer lokal; jangan `db:seed` di server publik.

`php artisan migrate:fresh --seed` membuat 4 akun utama dan 4 organisasi:

| Akun | Peran platform / organisasi |
| --- | --- |
| M. Azkal Azkia (`azkal@gmail.com`) | **Owner** platform, tidak masuk organisasi |
| M. Dwi Furqan (`furqan@gmail.com`) | HMMI=Admin, Kewirausahaan=Anggota, BEM=Editor, Freelancer=Admin |
| Raifandi Afriza (`fandi@gmail.com`) | HMMI=Editor, Kewirausahaan=Admin, BEM=Anggota, Freelancer=Anggota |
| M. Fadel Fadlurrahman Syabri (`fadel@gmail.com`) | HMMI=Anggota, Kewirausahaan=Editor, BEM=Admin, Freelancer=Editor |

Organisasi: **HMMI**, **HMMI Divisi Kewirausahaan**, **BEM**, **Komunitas Freelancer Digital Nusantara** (masing-masing punya kategori, 4 transaksi, kode undangan; Admin/Editor tidak ditagih, Anggota ditagih; tagihan 2026-11 anggota utama berstatus `belum`).

`php artisan kaskita:isi-demo` (hanya `local`/`testing`; menolak dijalankan dua kali atau tanpa seeder) menambah **24 akun** (`nama.lengkap@demo.kaskita.id`, terverifikasi, satu kata sandi demo), 32 keanggotaan (anggota baru pertama per organisasi = Editor, sisanya Anggota), tagihan 2026-10 dan 2026-11 berstatus campur tanpa berkas bukti, transaksi otomatis untuk tiap tagihan lunas, dan 4 transaksi tambahan per organisasi. Total: 28 akun, 44 keanggotaan. Perintah ini tidak dipanggil `DatabaseSeeder`.

**Tes** memakai `TestOrganizationSeeder` (fixture khusus tes: organisasi "BEM Fakultas Teknologi", kode `BEM26KAS`, akun `*.kaskita.demo`, kata sandi `KitaDemo2026!`; **jangan dipakai untuk data demo**).

**Pengujian:** 236 tes lulus (SQLite di memori). Seeder dan `kaskita:isi-demo` sudah berjalan tanpa error di MariaDB lokal. **Browser:** pemilik sudah mencoba login, daftar, Dashboard, Transaksi (versi sebelum I-1), hamburger (muncul di layar sempit), daftar Panel Owner, sebagian E2. **Belum dikonfirmasi di browser:** tampilan baru filter Transaksi (I-1: kesejajaran tombol, layar HP, nama bulan Indonesia), alur login 4 akun utama dan peran per organisasi (Azkal masuk panel Owner), menu hamburger terbuka saat diklik, `/` mengalihkan ke `/login`, tampilan Iuran/Anggota/Laporan (+Tutup buku)/Kategori/Undangan/Organisasi/Profil/Panel Owner baru, halaman auth baru, paginasi; alur E3; CSV (belum dibuka di Excel/LibreOffice). **Uji mutasi:** hanya E1 satu kali.

## 8. Keputusan aktif dan masalah terbuka

**Keputusan (lengkap di arsip):**
- **D1** Editor: transaksi, laporan tanpa tunggakan, Iuran (kecuali tagihan sendiri).
- **D2** Anggota boleh melihat dashboard dan transaksi; keterangan transaksi iuran tanpa nama; tunggakan hanya Admin.
- **D3/D12** Admin/Editor yang ingin membayar diberi `is_billable=true`.
- **D5** serah-terima Admin, promosi Admin, hapus organisasi: belum ada.
- **D7** Tailwind di Blade. **D8** locale `id`/timezone `Asia/Jakarta`: belum diubah.
- **D10/D11** bukti bayar iuran selesai (E3); nota transaksi manual menyusul.
- **D13** Laporan CSV saja. **D14** Tunggakan = semua tagihan `belum`+`menunggu` per hari ini. **D15** Tutup buku hanya bulan lampau, hanya Admin, bisa dibuka lagi.
- **D16** Tanpa enkripsi end-to-end. **D17** Owner platform terpisah dari peran organisasi.
- **D18** Akun/kata sandi demo tidak ditampilkan di UI; dicatat pemilik di luar repo.

**Masalah terbuka yang relevan:**
- Sidebar belum punya ikon.
- Login memakai input gaya manual (`rounded-[10px]`). Ganti ke `x-text-input`. (Dropdown "Jenis" di filter Transaksi sudah memakai `x-select-input`; `<select>` lain belum diperiksa.)
- Fitur prototipe belum ada di Laravel: filter kategori dan rentang tanggal serta ekspor CSV di Transaksi; lampiran nota (D10).
- Pencarian transaksi `q`: `%` dan `_` jadi wildcard (perbaikan: `addcslashes($q, '%_\\')`).
- **Zona waktu (D8):** `tanggal` pelunasan memakai hari ini (UTC); bulan bawaan laporan dan F2 memakai jam server.
- "Lunas tunai" pada tagihan `menunggu` mengubah `method` jadi tunai dan bukti tetap tersimpan.
- Laporan: daftar transaksi tidak dipaginasi; CSV berkoma bisa terbuka dalam satu kolom di Excel berlokal Indonesia.
- Non-anggota mendapat 403 untuk organisasi yang ada dan 404 untuk yang tidak ada (usul: 404 keduanya).
- Bila Admin menambahkan email Owner sebagai kontak, akun Owner bisa tertaut saat verifikasi (celah kecil, dibiarkan).
- Seeder dan `IsiDemo` memuat kata sandi tetap di kode: jangan dijalankan di server sungguhan.
- `README.md` basi; `.env` pernah tertempel di percakapan (saran: `php artisan key:generate`, ganti kata sandi DB).

## 9. Langkah berikutnya (satu langkah per percakapan)

**Selesai:** A-H, I-0 (data demo, `kaskita:isi-demo`, redirect `/`, login bersih), I-1 (filter Transaksi dirapikan, 236 tes). Pastikan commit/push sudah dilakukan (`git log`, `git status --short`).

### Langkah I — Peninjauan dan penutup (berikutnya)
1. **Telusur browser** halaman yang belum dicoba (bagian 7), termasuk tampilan filter Transaksi baru, per peran (login ke-4 akun utama, pindah organisasi), termasuk layar HP. Laporkan yang janggal per halaman.
2. Kecilkan sisa tampilan: ikon sidebar, seragamkan input Login (`x-text-input`).
3. Perbaiki `q` wildcard (kecil, ada tes), lalu pertimbangkan filter tambahan Transaksi dan nota (D10).
4. **Terakhir:** atur D8 (locale/timezone), perbarui `README.md`, syarat lingkungan seeder, uji di MariaDB, uji mutasi otorisasi F2 dan `EnsureOwner`, Pull Request ke `main` (perhatikan GitHub Pages), tinjau masalah terbuka.