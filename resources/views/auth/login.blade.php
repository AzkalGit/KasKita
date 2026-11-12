<x-guest-layout>
    <h2 class="text-2xl font-extrabold tracking-tight text-ink">Masuk</h2>
    <p class="mt-1 text-mute">Masuk ke akun KasKita kamu.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs font-bold text-ink">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   placeholder="nama@organisasi.id"
                   class="mt-1.5 block w-full rounded-[10px] border-line bg-white px-3.5 py-2.5 text-sm placeholder:text-mute/60 focus:border-brand focus:ring-brand">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="block text-xs font-bold text-ink">Kata sandi</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1.5 block w-full rounded-[10px] border-line bg-white px-3.5 py-2.5 text-sm focus:border-brand focus:ring-brand">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-mute">
                <input id="remember_me" type="checkbox" name="remember"
                       class="rounded border-line text-brand focus:ring-brand">
                Ingat saya
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="font-semibold text-brand hover:text-brand-dark">Lupa kata sandi?</a>
            @endif
        </div>

        <button type="submit"
                class="w-full rounded-[10px] bg-brand px-4 py-3 text-sm font-bold text-white transition hover:bg-brand-dark">
            Masuk
        </button>
    </form>

    @if (app()->environment('local'))
        <div class="mt-5 rounded-xl border border-line bg-white/60 p-4">
            <div class="text-xs font-bold text-ink">Akun demo presentasi</div>
            <div class="mt-1 text-[11px] text-mute">Gunakan kata sandi yang sama untuk semua akun:</div>
            <div class="mt-2 rounded-md bg-soft px-3 py-2 font-mono text-xs font-bold text-deep">KitaDemo2026!</div>
            <ul class="mt-3 space-y-1.5 text-xs text-mute">
                <li>Owner/Dev · <b class="text-ink">owner@kaskita.demo</b></li>
                <li>Admin · <b class="text-ink">admin@kaskita.demo</b></li>
                <li>Editor · <b class="text-ink">editor@kaskita.demo</b></li>
                <li>Anggota · <b class="text-ink">anggota@kaskita.demo</b></li>
            </ul>
        </div>
    @endif

    @if (Route::has('register'))
        <p class="mt-6 text-center text-mute">
            Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-brand hover:text-brand-dark">Daftar</a>
        </p>
    @endif
</x-guest-layout>