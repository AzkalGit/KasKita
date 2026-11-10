<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Halaman "dashboard" bawaan Breeze diganti pengalihan ke daftar organisasi.
// Namanya dipertahankan karena banyak bagian Breeze (verifikasi email, dsb.) memakai route('dashboard').
Route::redirect('/dashboard', '/organisasi')->middleware(['auth', 'verified'])->name('dashboard');

// Profil (bawaan Breeze): cukup login, supaya email yang salah masih bisa diperbaiki sebelum diverifikasi
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Semua yang menyangkut organisasi butuh email terverifikasi
Route::middleware(['auth', 'verified'])->group(function () {
    // Daftar, buat, dan gabung organisasi
    Route::get('/organisasi', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organisasi', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organisasi/gabung', [OrganizationController::class, 'join'])->name('organizations.join');

    // Halaman di dalam satu organisasi: hanya untuk anggota aktif organisasi itu
    Route::middleware('org.member')
        ->prefix('organisasi/{organization}')
        ->scopeBindings() // {invitation} hanya dicari di dalam organisasi pada URL (kode organisasi lain = 404)
        ->group(function () {
            Route::get('/dashboard', [OrganizationDashboardController::class, 'show'])
                ->name('organizations.dashboard');

            // Kelola kode undangan (khusus Admin; dicek lewat Policy 'manageInvitations')
            Route::get('/undangan', [InvitationController::class, 'index'])->name('invitations.index');
            Route::post('/undangan', [InvitationController::class, 'store'])->name('invitations.store');
            Route::patch('/undangan/{invitation}/tutup', [InvitationController::class, 'close'])->name('invitations.close');
            Route::patch('/undangan/{invitation}/buka', [InvitationController::class, 'reopen'])->name('invitations.reopen');
            // Kelola anggota (khusus Admin, kecuali "keluar" yang boleh untuk anggota aktif mana pun)
            Route::get('/anggota', [MemberController::class, 'index'])->name('members.index');
            Route::post('/anggota', [MemberController::class, 'store'])->name('members.store');
            Route::post('/anggota/keluar', [MemberController::class, 'leave'])->name('members.leave');
            Route::patch('/anggota/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::patch('/anggota/{member}/peran', [MemberController::class, 'updateRole'])->name('members.role');
            Route::patch('/anggota/{member}/nonaktifkan', [MemberController::class, 'deactivate'])->name('members.deactivate');
            Route::patch('/anggota/{member}/aktifkan', [MemberController::class, 'reactivate'])->name('members.reactivate');
            // Kategori transaksi (khusus Admin; dicek lewat Policy 'manageSettings')
            Route::get('/kategori', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('/kategori', [CategoryController::class, 'store'])->name('categories.store');
            Route::patch('/kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            // Transaksi: semua anggota aktif boleh melihat; Admin/Editor mencatat dan mengubah; hanya Admin menghapus
            Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
            Route::post('/transaksi', [TransactionController::class, 'store'])->name('transactions.store');
            Route::get('/transaksi/{transaction}/ubah', [TransactionController::class, 'edit'])->name('transactions.edit');
            Route::patch('/transaksi/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
            Route::delete('/transaksi/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
            Route::post('/undangan/{invitation}/ganti', [InvitationController::class, 'regenerate'])->name('invitations.regenerate');
        });
});

require __DIR__.'/auth.php';
