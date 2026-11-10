<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ubah transaksi · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('transactions.update', [$organization, $transaction]) }}"
                  class="bg-white shadow sm:rounded-lg p-6 grid md:grid-cols-2 gap-4">
                @csrf @method('PATCH')
                @include('transactions._fields', ['transaction' => $transaction, 'categories' => $categories])
                <div class="md:col-span-2 flex items-center gap-4">
                    <x-primary-button>Simpan perubahan</x-primary-button>
                    <a href="{{ route('transactions.index', $organization) }}" class="text-sm text-gray-600">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>