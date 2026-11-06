<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\InvitationUse;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    // Daftar organisasi milik pengguna yang sedang login
    public function index(Request $request)
    {
        $memberships = $request->user()->memberships()
            ->where('is_active', true)
            ->with('organization')
            ->get();

        return view('organizations.index', compact('memberships'));
    }

    // Buat organisasi baru: pembuat otomatis menjadi Admin
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($data, $user) {
            $org = Organization::create([
                'name' => $data['name'],
                'created_by' => $user->id,
            ]);

            // Admin tidak ditagih iuran, sama seperti data demo
            $org->members()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
                'is_billable' => false,
            ]);

            // Kategori bawaan yang dipakai modul iuran
            $org->categories()->create(['name' => 'Iuran', 'is_system' => true]);

            // Kode awal berlaku 7 hari (tidak permanen); Admin bisa membuat/menutup/mengganti kode di halaman Undangan
            Invitation::issue($org, Invitation::DEFAULT_DAYS, $user);
        });

        return redirect()->route('organizations.index')
            ->with('status', 'Organisasi berhasil dibuat. Kamu menjadi Admin.');
    }

    // Gabung lewat kode undangan: peran selalu Anggota
    public function join(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $key = 'join-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $menit = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->withErrors(['code' => "Terlalu banyak percobaan. Coba lagi dalam {$menit} menit."]);
        }

        $invitation = Invitation::with('organization')
            ->where('code', strtoupper(trim($request->code)))
            ->first();

        if (! $invitation || ! $invitation->isUsable() || ! $invitation->organization->isActive()) {
            RateLimiter::hit($key, 900); // hitung percobaan gagal, jendela 15 menit

            return back()->withErrors(['code' => 'Kode tidak valid, sudah ditutup, atau sudah kedaluwarsa.']);
        }

        $org = $invitation->organization;

        $existing = $org->members()->where('user_id', $user->id)->first();
        if ($existing) {
            $pesan = $existing->is_active
                ? 'Kamu sudah menjadi anggota organisasi ini.'
                : 'Keanggotaanmu di organisasi ini dinonaktifkan. Hubungi Admin.';

            return back()->withErrors(['code' => $pesan]);
        }

        // Kalau Admin sudah mencatat emailnya sebagai kontak, hubungkan ke baris itu,
        // kecuali baris itu sudah dinonaktifkan (mis. bekas anggota yang dikeluarkan).
        $pending = $org->members()
            ->whereNull('user_id')
            ->where('email', Str::lower($user->email))
            ->first();

        if ($pending && ! $pending->is_active) {
            return back()->withErrors(['code' => 'Keanggotaanmu di organisasi ini dinonaktifkan. Hubungi Admin.']);
        }

        DB::transaction(function () use ($org, $user, $invitation, $pending) {
            $member = $pending;

            if ($member) {
                $member->update([
                    'user_id' => $user->id,
                    'role' => $member->role ?? 'anggota',
                ]);
            } else {
                $org->members()->create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'anggota',
                ]);
            }

            InvitationUse::create([
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'used_at' => now(),
            ]);
        });

        RateLimiter::clear($key);

        return redirect()->route('organizations.index')
            ->with('status', "Kamu bergabung ke {$org->name} sebagai Anggota.");
    }
}
