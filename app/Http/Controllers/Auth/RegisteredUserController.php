<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // HANYA di lingkungan lokal: email langsung dianggap terverifikasi supaya
        // pengembangan tidak ribet (MAIL_MAILER=log tidak mengirim email sungguhan).
        // Event Verified dipicu agar kontak anggota yang emailnya sama tetap tertaut
        // (App\Listeners\LinkPendingMemberships). Di lingkungan lain verifikasi tetap wajib.
        if (app()->environment('local')) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Penautan ke data anggota (kontak) yang emailnya sama TIDAK dilakukan di sini
        // untuk lingkungan non-lokal. Itu baru terjadi setelah email diverifikasi.

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('organizations.index', absolute: false));
    }
}
