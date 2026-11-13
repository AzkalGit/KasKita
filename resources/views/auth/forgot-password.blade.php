<x-guest-layout>
    <div class="mb-4 text-mute">
        Lupa kata sandi? Tidak masalah. Masukkan alamat emailmu dan kami kirimkan tautan untuk memilih kata sandi baru.
    </div>

    <!-- Status sesi -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email -->
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-end">
            <x-primary-button>Kirim tautan atur ulang kata sandi</x-primary-button>
        </div>
    </form>
</x-guest-layout>