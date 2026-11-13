<x-guest-layout>
    <h2 class="text-2xl font-extrabold tracking-tight text-ink">Lupa kata sandi</h2>
    <p class="mt-1 text-mute">Masukkan alamat emailmu dan kami kirimkan tautan untuk memilih kata sandi baru.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Kirim tautan atur ulang</x-primary-button>
    </form>

    <p class="mt-6 text-center text-mute">
        <a href="{{ route('login') }}" class="font-bold text-brand hover:text-brand-dark">← Kembali ke halaman masuk</a>
    </p>
</x-guest-layout>