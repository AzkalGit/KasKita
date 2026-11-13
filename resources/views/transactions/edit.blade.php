<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Ubah transaksi</h1>
        <p class="mt-0.5 text-mute">{{ $organization->name }}</p>
    </x-slot>

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('transactions.update', [$organization, $transaction]) }}"
              class="grid gap-4 rounded-2xl border border-line bg-white p-5 md:grid-cols-2">
            @csrf @method('PATCH')
            @include('transactions._fields', ['transaction' => $transaction, 'categories' => $categories])
            <div class="flex items-center gap-4 md:col-span-2">
                <x-primary-button>Simpan perubahan</x-primary-button>
                <a href="{{ route('transactions.index', $organization) }}" class="text-sm font-semibold text-mute hover:text-ink">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>