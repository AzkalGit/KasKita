<x-guest-layout>
    <h2 class="text-2xl font-extrabold tracking-tight text-ink">Konfirmasi kata sandi</h2>
    <p class="mt-1 text-mute">Ini area aman aplikasi. Konfirmasi kata sandimu sebelum melanjutkan.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="password" value="Kata sandi" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Konfirmasi</x-primary-button>
    </form>
</x-guest-layout>