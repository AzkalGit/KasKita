<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\ProfileController;
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
            Route::post('/undangan/{invitation}/ganti', [InvitationController::class, 'regenerate'])->name('invitations.regenerate');
        });
});

require __DIR__.'/auth.php';
