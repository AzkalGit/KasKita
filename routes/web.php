<?php

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
        ->group(function () {
            Route::get('/dashboard', [OrganizationDashboardController::class, 'show'])
                ->name('organizations.dashboard');
        });
});

require __DIR__.'/auth.php';
