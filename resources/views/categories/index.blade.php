<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Kategori</h1>
        <p class="mt-0.5 text-mute">Kelompok transaksi untuk {{ $organization->name }}.</p>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @if ($errors->has('category'))
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">{{ $errors->first('category') }}</div>
        @endif

        {{-- Tambah kategori --}}
        <form method="POST" action="{{ route('categories.store', $organization) }}"
              class="space-y-4 rounded-2xl border border-line bg-white p-5">
            @csrf
            <h3 class="text-base font-bold">Tambah kategori</h3>
            <div>
                <x-input-label for="name" value="Nama kategori" />
                <x-text-input id="name" name="name" class="mt-1 block w-full max-w-md" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <x-primary-button>Tambah</x-primary-button>
        </form>

        {{-- Daftar kategori --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-2 text-base font-bold">Daftar kategori ({{ $categories->count() }})</h3>

            @foreach ($categories as $c)
                <div class="border-b border-line py-3.5 last:border-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="font-semibold">{{ $c->name }}</span>
                            @if ($c->is_system)
                                <x-badge tone="gray" class="ml-2">Bawaan sistem</x-badge>
                            @endif
                            <div class="text-xs text-mute">{{ $c->transactions_count }} transaksi</div>
                        </div>

                        @unless ($c->is_system)
                            <div class="flex items-center gap-4 font-semibold">
                                <details>
                                    <summary class="cursor-pointer text-brand marker:content-none hover:text-brand-dark">Ubah nama</summary>
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
                                        <button class="text-expense hover:opacity-80">Hapus</button>
                                    </form>
                                @else
                                    <span class="text-xs font-normal text-mute">Dipakai transaksi</span>
                                @endif
                            </div>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>