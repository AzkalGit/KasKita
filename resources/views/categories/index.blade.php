<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Kategori · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->has('category'))
                <div class="p-4 bg-red-50 text-red-800 rounded">{{ $errors->first('category') }}</div>
            @endif

            {{-- Tambah kategori --}}
            <form method="POST" action="{{ route('categories.store', $organization) }}"
                  class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                @csrf
                <h3 class="font-semibold">Tambah kategori</h3>
                <div>
                    <x-input-label for="name" value="Nama kategori" />
                    <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <x-primary-button>Tambah</x-primary-button>
            </form>

            {{-- Daftar kategori --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Daftar kategori ({{ $categories->count() }})</h3>

                @foreach ($categories as $c)
                    <div class="border-b py-3 last:border-0">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="font-medium">{{ $c->name }}</span>
                                @if ($c->is_system)
                                    <span class="ml-2 text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-600">Bawaan sistem</span>
                                @endif
                                <div class="text-sm text-gray-500">{{ $c->transactions_count }} transaksi</div>
                            </div>

                            @unless ($c->is_system)
                                <div class="flex items-center gap-4">
                                    <details>
                                        <summary class="text-sm text-indigo-600 cursor-pointer">Ubah nama</summary>
                                        <form method="POST" action="{{ route('categories.update', [$organization, $c]) }}"
                                              class="mt-2 flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <x-text-input name="name" :value="$c->name" required />
                                            <x-primary-button>Simpan</x-primary-button>
                                        </form>
                                    </details>

                                    @if ($c->transactions_count === 0)
                                        <form method="POST" action="{{ route('categories.destroy', [$organization, $c]) }}"
                                              onsubmit="return confirm({{ Js::from('Hapus kategori '.$c->name.'?') }})">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-red-600 font-medium">Hapus</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">Dipakai transaksi</span>
                                    @endif
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('organizations.dashboard', $organization) }}" class="text-sm text-indigo-600">← Dashboard</a>
        </div>
    </div>
</x-app-layout>