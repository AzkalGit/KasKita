<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Transaksi</h1>
        <p class="mt-0.5 text-mute">Semua uang masuk dan keluar {{ $organization->name }}.</p>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @if ($errors->has('transaction'))
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">{{ $errors->first('transaction') }}</div>
        @endif

        {{-- Catat transaksi (Admin dan Editor) --}}
        @if ($canManage)
            <details class="group rounded-2xl border border-line bg-white p-5"
                     @if ($errors->hasAny(['type', 'category_id', 'amount', 'tanggal', 'description'])) open @endif>
                <summary class="cursor-pointer text-base font-bold text-brand marker:content-none group-open:mb-4">+ Tambah transaksi</summary>
                <form method="POST" action="{{ route('transactions.store', $organization) }}"
                      class="grid gap-4 md:grid-cols-2">
                    @csrf
                    @include('transactions._fields', ['transaction' => null, 'categories' => $categories])
                    <div class="md:col-span-2"><x-primary-button>Simpan transaksi</x-primary-button></div>
                </form>
            </details>
        @endif

        {{-- Filter --}}
        <form method="GET" action="{{ route('transactions.index', $organization) }}"
              class="grid items-end gap-4 rounded-2xl border border-line bg-white p-5 md:grid-cols-4">
            <div>
                <x-input-label for="f_month" value="Bulan" />
                <x-text-input id="f_month" name="month" type="month" class="mt-1 block w-full"
                              :value="$filters['month'] ?? ''" />
            </div>
            <div>
                <x-input-label for="f_type" value="Jenis" />
                <select id="f_type" name="type" class="mt-1 block w-full rounded-xl border-line bg-white text-ink shadow-sm focus:border-brand focus:ring-brand">
                    <option value="">Semua</option>
                    <option value="masuk" @selected(($filters['type'] ?? '') === 'masuk')>Pemasukan</option>
                    <option value="keluar" @selected(($filters['type'] ?? '') === 'keluar')>Pengeluaran</option>
                </select>
            </div>
            <div>
                <x-input-label for="f_q" value="Cari keterangan" />
                <x-text-input id="f_q" name="q" class="mt-1 block w-full" :value="$filters['q'] ?? ''" />
            </div>
            <div class="flex items-center gap-3">
                <x-primary-button>Terapkan</x-primary-button>
                <a href="{{ route('transactions.index', $organization) }}" class="text-sm font-semibold text-mute hover:text-ink">Reset</a>
            </div>
            <x-input-error :messages="$errors->get('month')" class="md:col-span-4" />
        </form>

        {{-- Daftar --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold">Daftar transaksi ({{ $transactions->total() }})</h3>
                <div class="text-sm text-mute">
                    Pemasukan <strong class="text-income">Rp {{ number_format($masuk, 0, ',', '.') }}</strong>
                    · Pengeluaran <strong class="text-expense">Rp {{ number_format($keluar, 0, ',', '.') }}</strong>
                </div>
            </div>

            @if ($transactions->isEmpty())
                <p class="text-mute">Tidak ada transaksi yang cocok.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead>
                            <tr class="border-b border-line text-xs font-bold uppercase tracking-wider text-mute">
                                <th class="py-2.5 pr-3">Tanggal</th>
                                <th class="py-2.5 pr-3">Keterangan</th>
                                <th class="py-2.5 pr-3">Kategori</th>
                                <th class="py-2.5 pr-3 text-right">Jumlah</th>
                                @if ($canManage || $canDelete)
                                    <th class="py-2.5 text-right"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $t)
                                <tr class="border-b border-line last:border-0">
                                    <td class="whitespace-nowrap py-3 pr-3 text-mute">{{ $t->tanggal->format('d/m/Y') }}</td>
                                    <td class="py-3 pr-3">
                                        <div>{{ $t->description }}</div>
                                        @if ($t->isLocked())
                                            <span class="mt-1 inline-block rounded-full bg-soft px-2 py-0.5 text-xs font-bold text-deep">Otomatis dari iuran</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3">
                                        <span class="rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold text-mute">{{ $t->category->name }}</span>
                                    </td>
                                    <td class="whitespace-nowrap py-3 pr-3 text-right font-bold {{ $t->type === 'masuk' ? 'text-income' : 'text-expense' }}">
                                        {{ $t->type === 'masuk' ? '+' : '−' }}Rp {{ number_format($t->amount, 0, ',', '.') }}
                                    </td>
                                    @if ($canManage || $canDelete)
                                        <td class="whitespace-nowrap py-3 text-right">
                                            @if (! $t->isLocked())
                                                <div class="flex items-center justify-end gap-4 text-sm font-semibold">
                                                    @if ($canManage)
                                                        <a href="{{ route('transactions.edit', [$organization, $t]) }}" class="text-brand hover:text-brand-dark">Ubah</a>
                                                    @endif
                                                    @if ($canDelete)
                                                        <form method="POST" action="{{ route('transactions.destroy', [$organization, $t]) }}"
                                                              onsubmit="return confirm({{ Js::from('Hapus transaksi '.$t->description.'?') }})">
                                                            @csrf @method('DELETE')
                                                            <button class="text-expense hover:opacity-80">Hapus</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4">{{ $transactions->links() }}</div>
        </div>
    </div>
</x-app-layout>