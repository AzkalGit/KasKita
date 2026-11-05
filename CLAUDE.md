# CLAUDE.md — KasKita

Panduan untuk Claude (dan siapa pun) yang melanjutkan proyek ini. Baca seluruhnya sebelum mengubah kode.

Disusun dari pembacaan seluruh kode pada commit `4a99c24` (9 Okt 2026), ditambah pengujian perilaku dengan simulasi DOM (jsdom). Belum pernah diuji di browser sungguhan.

## 1. Ringkasan

- **Apa:** aplikasi kas organisasi. Satu akun bisa ikut banyak organisasi; peran (Admin, Editor, Anggota) ditentukan **per organisasi**; Owner/Dev adalah peran **level platform**.
- **Sekarang:** prototipe statis (HTML + CSS + satu `js/kaskita.js`), data di `localStorage`. Tujuannya menguji desain dan alur.
- **Berikutnya:** diimplementasikan di **Laravel** (dijalankan lokal lewat Laragon, database MySQL). Konsep sudah ditentukan; yang tersisa adalah implementasi. Lihat bagian 9.
- **Bahasa:** semua teks antarmuka dan komentar kode dalam **Bahasa Indonesia**. Balas pengguna dalam Bahasa Indonesia.
- **Sumber kebenaran:** kode di repo ini. `KASKITA-STATUS.txt` adalah catatan sesi sebelumnya. Jika keduanya berbeda, kode yang benar. **Jangan menggabungkan file dari ZIP/versi berbeda tanpa membandingkan perubahan lebih dulu.**

## 2. Struktur berkas

```
index.html          pengalihan ke login.html
login.html          masuk (memuat daftar akun demo)
register.html       daftar akun (tanpa memilih organisasi)
organisasi.html     daftar organisasi, buat, gabung dengan kode
dashboard.html      ringkasan
transaksi.html      daftar transaksi + dialog tambah/ubah/hapus
iuran.html          tagihan (tampilan kelola Admin / tampilan pribadi Anggota)
anggota.html        anggota, peran, kode undangan
laporan.html        laporan, ekspor CSV, cetak
pengaturan.html     saldo awal, iuran, info bayar, kategori
owner.html          panel Owner/Dev (metadata saja)
css/kaskita.css     semua gaya
js/kaskita.js       semua logika (~570 baris, satu berkas, tanpa modul/bundler)
KASKITA-STATUS.txt  catatan teknis sesi sebelumnya
```

Tidak ada `package.json`, tes otomatis, atau `.gitignore`. Tidak ada dependensi eksternal selain Google Fonts.

## 3. Arsitektur `js/kaskita.js`

- **Satu skrip untuk semua halaman.** Setiap HTML memuatnya di akhir `<body>`. Halaman ditentukan oleh `<body data-page="...">`, dan kode memakai blok `if(page==='...'){...}`. Halaman login/daftar memakai `data-page` kosong (`page==null`).
- **Urutan berkas** (jangan diacak, banyak bagian bergantung pada bagian sebelumnya):
  1. Konstanta dan utilitas dasar, lalu model data dan `migrateLegacy()`, `ensureDemo()`.
  2. `me` (sesi), `role`, flag `isAdmin/isEditor/isMember/isPlatformOwner`, lalu **guard akses** (`location.replace` + `throw`).
  3. `save()`, helper data, helper tanggal/periode, utilitas tampilan.
  4. Kerangka halaman: sidebar dibuat lewat JS dan isi `body>.content` dipindahkan ke `#main`.
  5. Blok per halaman: dashboard, transaksi, anggota, iuran, laporan, pengaturan, organisasi, owner, login/daftar.
- **Guard akses** memakai `throw new Error(...)` setelah `location.replace(...)` supaya sisa skrip berhenti. Peran sebenarnya dihitung dari `user.memberships` saat halaman dimuat, **bukan** dari `session.peran` (itu hanya informasi tampilan).
- **Dialog** (`#dlgK` konfirmasi, `#dlgImg` pratinjau gambar) **diduplikasi manual di tiap HTML**; tidak ada partial. Halaman yang memanggil `konfirmasi()` atau `lihatGambar()` harus memuat dialognya sendiri.
- **Variabel global.** Skrip memakai `const` tingkat atas (satu skrip per halaman, jadi aman di browser). `bendahara` adalah alias lama untuk `isAdmin` dan masih dipakai di beberapa tempat.
- **Pola umum satu halaman:** `draw()` merender ulang dari `org` (objek di memori), aksi mengubah `org`, lalu `if(save()){draw();toast(...)}`.

## 4. Model data (localStorage)

| Kunci | Isi |
| --- | --- |
| `kaskitaUsers` | Array akun: `{id, nama, email, pass, platformRole, memberships:[{orgId, role}]}`. `pass` **polos**. `platformRole:'owner'` hanya untuk Owner/Dev. |
| `kaskitaOrganizations` | "Direktori" organisasi: `{id, nama, createdAt, status:'aktif'\|'nonaktif', joinCode, creatorId}`. Digandakan di dokumen organisasi; `save()` menyinkronkannya. |
| `kaskitaOrgId:<id>` | Dokumen satu organisasi (lihat di bawah). |
| `kaskitaSession` | `{userId, email, nama, platformRole, currentOrgId, org, peran}`. |
| `kaskitaPeriode` | `'semua'` atau `'YYYY-MM'`; **dibagi antar halaman, akun, dan organisasi** pada browser yang sama. |
| `kaskitaOrg:<slug>`, `kaskitaData:<email>` | Kunci **lama** (Tahap 1–2). Hanya dibaca oleh `migrateLegacy()`; tidak dihapus setelah migrasi. |

Dokumen organisasi (`kaskitaOrgId:<id>`):

```
{ id, nama, createdAt, status, joinCode, creatorId,
  saldoAwal, iuran, infoBayar, kategori:[...],
  anggota:[{id, nama, email, telp, aktif}],            // kontak yang ditagih
  tagihan:[{id, anggotaId, bulan:'YYYY-MM', nominal,
            status:'belum'|'menunggu'|'lunas',
            bukti:{data,nama,tgl}|null, ditolak, tglLunas, via:'transfer'|'tunai', txId}],
  transaksi:[{id, tgl:'YYYY-MM-DD', ket, kat, tipe:'masuk'|'keluar', jml, nota?, tagihanId?}] }
```

Konsep penting:

- **Dua hal berbeda, ditautkan lewat email (tanpa huruf besar/kecil):** `org.anggota[]` adalah *kontak yang ditagih* (boleh belum punya akun), sedangkan `user.memberships[]` adalah *akses akun beserta perannya*. Anggota "punya akun" jika ada user dengan email itu yang memiliki membership di organisasi tersebut.
- **Peran tinggal di `user.memberships[].role`:** `'admin' | 'editor' | 'anggota'`. Nilai lama `'bendahara'` dan `'member'` masih dikenali sebagai alias.
- **Transaksi otomatis:** saat tagihan disetujui atau ditandai lunas, `setLunas()` menambah transaksi `kat:'Iuran'` dengan `tagihanId`, dan tagihan menyimpan `txId`. Transaksi bertanda `tagihanId` **tidak bisa diubah atau dihapus** dari halaman Transaksi. `batalLunas()` menghapusnya dan mengembalikan status ke `menunggu` (bila ada bukti) atau `belum`.
- **Saldo** = `saldoAwal` + pemasukan − pengeluaran (dihitung, tidak disimpan).
- **Kategori `Iuran` bersifat tetap** (dipakai modul iuran). Kategori yang sudah dipakai transaksi tidak bisa dihapus.
- **Tanggal disimpan dan diproses sebagai teks** (`'YYYY-MM-DD'`, `slice(0,7)` untuk bulan) agar bebas zona waktu. Jangan diganti dengan `new Date(string)`.
- **Uang** disimpan sebagai bilangan bulat rupiah (`Math.round`).
- **Lampiran** gambar diperkecil lewat canvas (maks 900 px, JPEG kualitas 0,72) lalu disimpan sebagai data URL di dalam dokumen organisasi. Selain gambar ditolak.
- **ID baris** numerik `Date.now()*1000+acak`; ID organisasi `org-<angka>` (demo: `demo-bem-fakultas`; hasil migrasi data lama: `legacy-<slug>`), ID akun `user-<angka>`.
- **Keamanan tampilan:** semua teks pengguna lewat `esc()`; ekspor CSV memberi awalan `'` pada sel yang diawali `= + - @` (cegah injeksi rumus).

## 5. Peran dan hak akses (sesuai kode saat ini)

| Halaman / aksi | Owner/Dev | Admin | Editor | Anggota |
| --- | :-: | :-: | :-: | :-: |
| `owner.html` | ya | – | – | – |
| `organisasi.html` | dialihkan ke owner | ya | ya | ya |
| dashboard, transaksi (lihat) | – | ya | ya | dialihkan ke iuran |
| transaksi: catat/ubah | – | ya | ya | – |
| transaksi: hapus | – | ya | – | – |
| `iuran.html` kelola + verifikasi | – | ya | dialihkan ke dashboard | – |
| `iuran.html` pribadi (upload bukti) | – | – | – | ya |
| `anggota.html`, kode undangan | – | ya | dialihkan | dialihkan |
| `pengaturan.html` | – | ya | dialihkan | dialihkan |
| `laporan.html` | – | ya (+ tunggakan) | ya (tanpa tunggakan) | dialihkan |

Aturan lain yang sudah diterapkan:

- Owner/Dev yang membuka halaman selain `owner.html` dialihkan ke `owner.html`; tanpa membership di organisasi aktif, pengguna dialihkan ke `organisasi.html`.
- **Organisasi nonaktif** tidak bisa dibuka (dialihkan ke `organisasi.html`) dan tidak menerima anggota baru; datanya tidak dihapus dan bisa diaktifkan lagi oleh Owner/Dev.
- Pembuat organisasi otomatis Admin; bergabung lewat kode memberi peran Anggota.
- Admin hanya bisa mengubah Anggota ↔ Editor lewat dropdown. Peran Admin tidak bisa diubah dan anggota ber-peran Admin tidak bisa dihapus.
- Anggota yang punya iuran lunas tidak bisa dihapus (harus dinonaktifkan).
- **Panel Owner/Dev hanya menampilkan metadata** (nama, tanggal dibuat, jumlah anggota, status) dan tombol aktif/nonaktif serta reset data demo. **Jangan pernah** menambahkan saldo, transaksi, tagihan, atau bukti ke panel ini.

## 6. Data demo

Dibuat otomatis oleh `ensureDemo()` setiap halaman dimuat (hanya jika belum ada). Kata sandi semua akun: `KitaDemo2026!`.

| Peran | Email |
| --- | --- |
| Owner/Dev | `owner@kaskita.demo` |
| Admin (Nadia Putri) | `admin@kaskita.demo` |
| Editor (Rafi Pratama) | `editor@kaskita.demo` |
| Anggota (Dimas Saputra) | `anggota@kaskita.demo` |

Organisasi demo: **BEM Fakultas Teknologi**, ID `demo-bem-fakultas`, kode `BEM26KAS`. Berisi 6 transaksi (dua di antaranya otomatis dari iuran lunas) dan 4 tagihan (lunas, belum bayar, menunggu verifikasi dengan bukti SVG fiktif, lunas). "Reset data demo" (panel Owner/Dev) mengembalikan organisasi demo ke data awal dan **mengatur ulang keanggotaan tiga akun demo hanya ke organisasi demo** (keanggotaan lain milik akun-akun itu, jika ada, ikut hilang). Organisasi lain tidak disentuh.

## 7. Konsep yang sudah ditentukan dan statusnya

| Konsep | Status |
| --- | --- |
| Akun dipisah dari organisasi; daftar dulu, lalu buat atau gabung | **Ada** |
| Satu akun banyak organisasi; peran per organisasi | **Ada** |
| Pembuat organisasi otomatis Admin | **Ada** |
| Gabung lewat kode undangan, peran Anggota | **Ada**, tetapi lihat bagian 8 (kode permanen, tanpa batas percobaan) |
| Admin mengubah Anggota ↔ Editor | **Ada** |
| Admin mempromosikan ke Admin / memindahkan kewenangan | **Belum** |
| Keluar dari organisasi; hapus organisasi | **Belum** |
| Owner/Dev: panel terpisah, hanya metadata, nonaktifkan organisasi | **Ada** |
| Transaksi otomatis dari iuran lunas, terkunci | **Ada** |
| Saldo awal, kategori dapat diatur, lampiran nota/bukti | **Ada** |
| Filter periode bulan di dashboard dan laporan, ekspor CSV, cetak | **Ada** |
| Laporan **per bulan dan per minggu**, bisa dipilih ulang dan diunduh | **Belum** (hanya filter bulan) |
| Minggu dipotong di batas bulan (total mingguan = total bulanan) | **Belum** (keputusan desain, belum dikodekan) |
| Rekap berisi saldo awal periode, pemasukan, pengeluaran, saldo akhir | **Sebagian** (saldo awal/akhir hanya total keseluruhan, belum per periode) |
| Unduhan PDF berkop organisasi | **Belum** (hanya cetak browser) |
| Tutup buku (bulan yang ditutup tidak bisa diedit) | **Belum** |
| Riwayat aktivitas (siapa, apa, kapan) | **Belum** |
| Kode undangan berbatas waktu, bisa ditutup/dibuka | **Belum** |
| Tampilan HP yang dirancang untuk Anggota | **Belum diverifikasi** |

## 8. Masalah yang diketahui dan keputusan terbuka

Diverifikasi dengan menjalankan kode, bukan hanya membaca.

**Bug**

1. **`seed` tidak terdefinisi.** `bukaContoh()` (`kaskita.js` baris ~236) memakai `seed`, tetapi variabel itu tidak ada di berkas. Klik "Isi data contoh" pada organisasi yang masih kosong menimbulkan `ReferenceError: seed is not defined`. Tombol itu tampil di dashboard dan laporan untuk organisasi baru. Penyebab: `seed` (10 transaksi contoh) ada di commit `f3c2768` dan ikut terhapus di commit `5db9e98` (penambahan peran), sedangkan tombol dan pemanggilnya tertinggal. Perbaikan: kembalikan `seed` dari `f3c2768` (`git show f3c2768:js/kaskita.js`) atau hapus tombolnya.
2. **Editor yang ikut ditagih iuran menemui jalan buntu.** Jika Editor punya data anggota dengan tagihan belum dibayar, dashboard menampilkan pengingat "Kamu punya N tagihan belum dibayar" yang mengarah ke `iuran.html`, padahal halaman itu menutup akses Editor. Admin dan Editor yang ikut ditagih juga tidak punya tempat mengunggah bukti (tampilan pribadi hanya untuk Anggota). Data demo tidak memicu ini karena Editor demo tidak punya tagihan.

**Perbedaan dengan konsep awal; perlu keputusan pemilik proyek**

3. **Hak Editor lebih sempit dari usulan awal.** Usulan awal: Editor boleh membuat tagihan dan memverifikasi iuran, dan boleh melihat daftar anggota (hanya-lihat). Kode sekarang: Editor tidak bisa membuka iuran, anggota, dan pengaturan. Kode ini mengikuti `KASKITA-STATUS.txt` butir 7. Jangan ubah salah satunya tanpa konfirmasi.
4. **Anggota hanya melihat halaman Iuran** (tidak bisa melihat dashboard, transaksi, atau laporan). Konsep awal menyebut "anggota hanya dapat view" yang bisa dibaca lebih luas. Konfirmasikan sebelum Laravel.
5. **Kode undangan** satu per organisasi, permanen, 6 karakter dari `Math.random()` (tanpa pengecekan duplikat antar organisasi), tidak ada tombol tutup/buka/buat ulang, dan **tidak ada batas percobaan**: 10 kode salah beruntun tidak dibatasi. Konsep awal menginginkan kode acak yang sulit ditebak, berbatas waktu, bisa ditutup, dan percobaan dibatasi.
6. **Admin menambah anggota dengan email milik akun lain langsung membuat keanggotaan** tanpa persetujuan pemilik akun. Di Laravel sebaiknya menjadi undangan yang harus diterima.
7. **Nama organisasi harus unik** di direktori browser ini. Di Laravel, putuskan apakah nama boleh kembar (bergabung memakai kode, jadi tidak wajib unik).

**Kekurangan kecil**

8. Kunci lama (`kaskitaOrg:<slug>`, `kaskitaData:<email>`) dan field lama pada akun (`org`, `peran`) tidak dibersihkan setelah migrasi. 
9. `register.html` masih menampilkan "Rp 4.280.000" yang tertulis tetap sebagai hiasan.
10. `kaskitaPeriode` dibagi lintas akun dan organisasi.
11. Pembatasan peran hanya di browser; kata sandi polos. Ini batasan prototipe, bukan bug, dan baru teratasi di Laravel.
12. Tidak ada tes otomatis di repo.

## pembaruan

8. : D2 diputuskan: Anggota boleh melihat saldo, total pemasukan, pengeluaran, dan transaksi terbaru, hanya lihat.
12. : Langkah E: tambahkan catatan “keterangan transaksi otomatis iuran tanpa nama anggota”.
11. 9. : dan kepala berkas: seperti yang kusebut di jawaban sebelumnya (entri Langkah A, hapus butir 1 di bagian 9, tes 66).

## 9. Panduan migrasi ke Laravel (usulan, belum diimplementasikan)

**Lingkungan:** Laragon (PHP, MySQL/MariaDB). Cek versi PHP yang dibutuhkan versi Laravel yang dipasang (versi terbaru umumnya meminta PHP 8.2+). Aktifkan HTTPS untuk alamat `.test` agar kata sandi tidak lewat tanpa enkripsi bila diakses dari perangkat lain.

**Pemetaan data → tabel:**

| Sekarang | Laravel |
| --- | --- |
| `kaskitaUsers` | `users` (`name`, `email` unik, `password` di-hash, `platform_role` nullable) |
| `kaskitaOrganizations` + dokumen org | `organizations` (`name`, `status`, `opening_balance`, `default_dues`, `payment_info`, `created_by`) |
| `org.anggota[]` + `user.memberships[]` | satu tabel `organization_members`: `organization_id`, `user_id` **nullable**, `name`, `email`, `phone`, `is_billable`, `role` nullable (`admin\|editor\|anggota`). Unik `(organization_id, user_id)` dan `(organization_id, email)`. |
| `org.kategori[]` | `categories` (`organization_id`, `name`, `is_system` untuk "Iuran") |
| `org.transaksi[]` | `transactions` (`tanggal` DATE, `type`, `amount` BIGINT rupiah, `category_id`, `invoice_id` nullable unik, `attachment_path`, `created_by`) |
| `org.tagihan[]` | `invoices` (`member_id`, `period` bulan, `amount`, `status`, `paid_at`, `method`, `rejection_reason`, `proof_path`). Unik `(member_id, period)`. |
| `joinCode` | `invitations` (`code` unik, `expires_at`, `closed_at`, `created_by`) + `invitation_uses` |
| (belum ada) | `activity_logs`, `period_closures` (tutup buku) |

**Aturan yang harus dipindahkan ke server (bukan hanya tampilan):**

- Satu `Policy` per sumber daya dengan matriks peran di bagian 5 (setelah keputusan butir 3–4 bagian 8). Semua query dibatasi `organization_id` organisasi aktif pengguna (global scope), dan setiap aksi dicek di server.
- Persetujuan iuran, pembatalan lunas, dan penghapusan tagihan dibungkus **transaksi database** karena menyentuh `invoices` dan `transactions` sekaligus. Satu kelas aksi per operasi (mis. `ApproveInvoice`, `RevertInvoice`).
- Transaksi bertanda `invoice_id` tidak bisa diubah atau dihapus dari alur transaksi biasa.
- Batas percobaan kode undangan lewat `RateLimiter`. Kode acak yang kuat, berbatas waktu, bisa ditutup; kode hanya memberi peran Anggota.
- Aturan peran: pembuat organisasi tidak bisa diturunkan atau dikeluarkan, organisasi harus selalu punya minimal satu Admin, dan tidak ada yang bisa mengubah perannya sendiri.
- Lampiran disimpan di disk privat (bukan data URL), divalidasi tipe dan ukuran di server, diunduh lewat route yang dicek policy.
- Hapus transaksi hanya Admin. Pertimbangkan *soft delete* dan catatan di `activity_logs`.

**Owner/Dev:** pisahkan controller dan route (`/owner/*`) yang **tidak pernah** menyentuh tabel `transactions`, `invoices`, atau lampiran, dan beri tes yang memastikannya. Perlu diakui terus terang: siapa pun yang memegang server dan database tetap bisa membaca data lewat phpMyAdmin. Yang realistis adalah tidak adanya fitur di aplikasi untuk mengintip, kata sandi ter-hash, dan panel yang hanya berisi metadata. Enkripsi ujung ke ujung tidak dipilih karena merusak laporan dan verifikasi di server.

**Laporan:** tanggal tetap `DATE`; pengelompokan bulan dengan `YYYY-MM`. Minggu dalam bulan dipotong di batas bulan. Saldo awal periode = `opening_balance` + semua transaksi sebelum periode itu. PDF lewat paket seperti dompdf. Semua peran organisasi yang boleh melihat laporan boleh mengunduhnya; tunggakan per anggota hanya untuk yang berhak mengelola iuran.

**Urutan yang disarankan:**

1. Fondasi: autentikasi, organisasi, anggota/peran, undangan, policy, layar pilih/buat/gabung.
2. Pindahkan fitur: transaksi, iuran, laporan, pengaturan, dengan tes untuk tiap aturan peran.
3. Pelengkap: lampiran di server, panel Owner/Dev, riwayat aktivitas, laporan mingguan dan PDF, tutup buku.

Tampilan dapat memakai Blade dari server (disarankan, sesuai rencana awal) daripada JavaScript yang memanggil API. Pertahankan kelas CSS dan struktur HTML yang ada agar desain tetap sama.

## 10. Aturan kerja

- **Baca dulu** berkas ini dan `KASKITA-STATUS.txt`, lalu bandingkan dengan kode sebelum menyimpulkan apa pun.
- **Pertahankan desain.** Ubah file yang ada; jangan menyusun ulang tampilan atau menambah dependensi tanpa diminta. Tetap tanpa bundler selama masih prototipe statis.
- **Teks antarmuka Bahasa Indonesia**, nada ramah dan jelas; pesan galat menjelaskan langkah berikutnya.
- **Jangan menambah data keuangan ke panel Owner/Dev** (bagian 5).
- **Jangan menyimpan atau menampilkan rahasia sungguhan.** Akun demo dan datanya fiktif dan boleh ada di repo; kunci/kata sandi nyata tidak boleh.
- **Jangan mengklaim sudah diuji di browser** bila hanya memakai simulasi. Katakan apa yang diuji dan bagaimana.
- **Perubahan peran/akses** harus mengikuti matriks bagian 5. Bila konsep berubah, perbarui bagian 5, bagian 7, README, dan `KASKITA-STATUS.txt` bersama-sama.
- **Perubahan model data localStorage** harus menyertakan migrasi dari bentuk sebelumnya (lihat `migrateLegacy()`), karena pengguna menyimpan data di browser mereka.

## 11. Cara memeriksa perubahan

```bash
node --check js/kaskita.js          # sintaks
```

Simulasi DOM (tanpa browser) yang dipakai untuk memverifikasi dokumen ini:

1. Pasang `jsdom` di folder sementara (`npm i jsdom`).
2. Untuk tiap halaman: baca HTML, buang tag `<link>` dan `<script src>`, buat `new JSDOM(html, {url, runScripts:'outside-only'})`.
3. Stub `localStorage` (dengan `length` dan `key(i)`), `HTMLDialogElement.prototype.showModal/close`, `alert`, `confirm`.
4. Evaluasi isi `kaskita.js` di window itu setelah mengganti `const`/`let` di awal baris menjadi `var`.
5. Isi form lalu kirim event `submit`, atau panggil `.click()`; periksa `localStorage`, isi DOM, dan galat yang tertangkap (`window.addEventListener('error', ...)`). Pengalihan halaman (`location.replace`) tidak berjalan di jsdom; guard terdeteksi dari galat yang dilempar.

Ini **tidak menggantikan** uji klik di browser nyata. Tata letak, responsivitas HP, dan cetak/PDF hanya bisa dinilai di browser.

Daftar periksa sebelum menyerahkan perubahan: sintaks lolos; tiap peran (empat profil demo) dicoba melewati halaman yang diizinkan dan ditolak; alur buat organisasi → gabung kode → ubah peran → buat tagihan → unggah bukti → setujui → transaksi otomatis muncul → batalkan; dokumen (README, bagian 5 dan 7) diperbarui bila perilaku berubah.
