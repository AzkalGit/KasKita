<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Transaksi · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->has('transaction'))
                <div class="p-4 bg-red-50 text-red-800 rounded">{{ $errors->first('transaction') }}</div>
            @endif

            {{-- Catat transaksi (Admin dan Editor) --}}
            @if ($canManage)
                <details class="bg-white shadow sm:rounded-lg p-6"
                         @if ($errors->hasAny(['type', 'category_id', 'amount', 'tanggal', 'description'])) open @endif>
                    <summary class="font-semibold cursor-pointer">Tambah transaksi</summary>
                    <form method="POST" action="{{ route('transactions.store', $organization) }}"
                          class="mt-4 grid md:grid-cols-2 gap-4">
                        @csrf
                        @include('transactions._fields', ['transaction' => null, 'categories' => $categories])
                        <div class="md:col-span-2"><x-primary-button>Simpan</x-primary-button></div>
                    </form>
                </details>
            @endif

            {{-- Filter --}}
            <form method="GET" action="{{ route('transactions.index', $organization) }}"
                  class="bg-white shadow sm:rounded-lg p-6 grid md:grid-cols-4 gap-4 items-end">
                <div>
                    <x-input-label for="f_month" value="Bulan" />
                    <x-text-input id="f_month" name="month" type="month" class="block mt-1 w-full"
                                  :value="$filters['month'] ?? ''" />
                </div>
                <div>
                    <x-input-label for="f_type" value="Jenis" />
                    <select id="f_type" name="type" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Semua</option>
                        <option value="masuk" @selected(($filters['type'] ?? '') === 'masuk')>Pemasukan</option>
                        <option value="keluar" @selected(($filters['type'] ?? '') === 'keluar')>Pengeluaran</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="f_q" value="Cari keterangan" />
                    <x-text-input id="f_q" name="q" class="block mt-1 w-full" :value="$filters['q'] ?? ''" />
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Terapkan</x-primary-button>
                    <a href="{{ route('transactions.index', $organization) }}" class="text-sm text-gray-600">Reset</a>
                </div>
                <x-input-error :messages="$errors->get('month')" class="md:col-span-4" />
            </form>

            {{-- Daftar --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex flex-wrap justify-between gap-4 mb-4 text-sm">
                    <div class="font-semibold">Daftar transaksi ({{ $transactions->total() }})</div>
                    <div>
                        Pemasukan <span class="text-green-700 font-semibold">Rp {{ number_format($masuk, 0, ',', '.') }}</span>
                        · Pengeluaran <span class="text-red-700 font-semibold">Rp {{ number_format($keluar, 0, ',', '.') }}</span>
                    </div>
                </div>

                @forelse ($transactions as $t)
                    <div class="border-b py-3 last:border-0">
                        <div class="flex justify-between gap-4">
                            <div>
                                <div>{{ $t->description }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $t->tanggal->format('d/m/Y') }} · {{ $t->category->name }}
                                    @if ($t->isLocked())
                                        · <span class="text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-600">Otomatis dari iuran</span>
                                    @endif
                                </div>
                            </div>
                            <div class="{{ $t->type === 'masuk' ? 'text-green-700' : 'text-red-700' }} whitespace-nowrap">
                                {{ $t->type === 'masuk' ? '+' : '−' }} Rp {{ number_format($t->amount, 0, ',', '.') }}
                            </div>
                        </div>

                        @if (! $t->isLocked() && ($canManage || $canDelete))
                            <div class="mt-2 flex items-center gap-4 text-sm">
                                @if ($canManage)
                                    <a href="{{ route('transactions.edit', [$organization, $t]) }}" class="text-indigo-600 font-medium">Ubah</a>
                                @endif
                                @if ($canDelete)
                                    <form method="POST" action="{{ route('transactions.destroy', [$organization, $t]) }}"
                                          onsubmit="return confirm({{ Js::from('Hapus transaksi '.$t->description.'?') }})">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 font-medium">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">Tidak ada transaksi yang cocok.</p>
                @endforelse

                <div class="mt-4">{{ $transactions->links() }}</div>
            </div>

            <a href="{{ route('organizations.dashboard', $organization) }}" class="text-sm text-indigo-600">← Dashboard</a>
        </div>
    </div>
</x-app-layout>