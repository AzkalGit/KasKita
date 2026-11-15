<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Pengaturan</h1>
        <p class="text-mute">{{ $organization->name }}</p>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-2xl border border-line bg-mint p-4 text-sm font-semibold">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-line bg-white p-5">
            <h2 class="mb-1 text-base font-bold">Info pembayaran iuran</h2>
            <p class="mb-4 text-sm text-mute">
                Teks ini tampil di halaman Iuran untuk semua anggota, misalnya nama bank, nomor rekening, dan nama pemilik rekening.
            </p>

            <form method="POST" action="{{ route('settings.update', $organization) }}">
                @csrf
                @method('PATCH')

                <x-input-label for="payment_info" value="Rekening / cara pembayaran" />
                <textarea
                    id="payment_info"
                    name="payment_info"
                    rows="4"
                    maxlength="500"
                    placeholder="Contoh: BSI 1234567890 a.n. Bendahara HMMI"
                    class="mt-1 block w-full rounded-xl border-line text-sm shadow-sm focus:border-brand focus:ring-brand"
                >{{ old('payment_info', $organization->payment_info) }}</textarea>
                <x-input-error :messages="$errors->get('payment_info')" class="mt-2" />

                <div class="mt-4">
                    <x-primary-button>Simpan</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
