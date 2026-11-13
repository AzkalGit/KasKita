<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Dashboard</h1>
                <p class="mt-0.5 text-mute">Ringkasan kas {{ $organization->name }}, semua periode.</p>
            </div>
            @can('manageTransactions', $organization)
                <a href="{{ route('transactions.index', $organization) }}"
                   class="rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark">+ Catat transaksi</a>
            @endcan
        </div>
    </x-slot>

    @if ($menunggu > 0)
        <a href="{{ route('invoices.index', $organization) }}"
           class="mb-4 block rounded-2xl border border-[#f1dd9c] bg-[#fff4d6] px-4 py-3 font-semibold text-[#7a5600] hover:bg-[#ffefbf]">
            {{ $menunggu }} bukti pembayaran iuran menunggu verifikasi →
        </a>
    @endif

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div class="rounded-2xl bg-deep p-5 text-white">
            <div class="font-semibold text-[#9ccfc9]">Saldo kas (semua periode)</div>
            <div class="mt-1.5 text-3xl font-extrabold tracking-tight">Rp {{ number_format($saldo, 0, ',', '.') }}</div>
            @if ($organization->opening_balance)
                <div class="mt-1.5 text-xs text-[#9ccfc9]">termasuk saldo awal Rp {{ number_format($organization->opening_balance, 0, ',', '.') }}</div>
            @endif
        </div>
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="font-semibold text-mute">Pemasukan</div>
            <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-income">Rp {{ number_format($masuk, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="font-semibold text-mute">Pengeluaran</div>
            <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-expense">Rp {{ number_format($keluar, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="font-semibold text-mute">Jumlah transaksi</div>
            <div class="mt-1.5 text-[22px] font-extrabold tracking-tight">{{ number_format($jumlah, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-3.5 text-base font-bold">Pemasukan dan pengeluaran</h3>
            <div class="flex h-[200px] items-end gap-3.5 pt-2.5">
                @foreach ($grafik as $b)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                         title="{{ $b['label'] }}: masuk Rp {{ number_format($b['masuk'], 0, ',', '.') }}, keluar Rp {{ number_format($b['keluar'], 0, ',', '.') }}">
                        <div class="flex w-full flex-1 items-end justify-center gap-1">
                            <i class="block w-[40%] max-w-[22px] rounded-t-md bg-income" style="height: {{ $b['tinggi_masuk'] }}%"></i>
                            <i class="block w-[40%] max-w-[22px] rounded-t-md bg-expense" style="height: {{ $b['tinggi_keluar'] }}%"></i>
                        </div>
                        <span class="text-xs text-mute">{{ $b['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-4 text-xs text-mute">
                <span><b class="mr-1.5 inline-block h-2.5 w-2.5 rounded-[3px] bg-income align-middle"></b>Pemasukan</span>
                <span><b class="mr-1.5 inline-block h-2.5 w-2.5 rounded-[3px] bg-expense align-middle"></b>Pengeluaran</span>
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5">
            <div class="mb-3.5 flex items-center justify-between">
                <h3 class="text-base font-bold">Transaksi terakhir</h3>
                @can('viewTransactions', $organization)
                    <a href="{{ route('transactions.index', $organization) }}" class="text-sm font-semibold text-brand hover:text-brand-dark">Lihat semua →</a>
                @endcan
            </div>
            @forelse ($terbaru as $t)
                <div class="flex justify-between gap-3 border-b border-line py-2.5 last:border-0">
                    <div class="min-w-0">
                        <div class="truncate">{{ $t->description }}</div>
                        <div class="text-xs text-mute">{{ $t->tanggal->format('d/m/Y') }} · {{ $t->category->name }}</div>
                    </div>
                    <strong class="whitespace-nowrap {{ $t->type === 'masuk' ? 'text-income' : 'text-expense' }}">
                        {{ $t->type === 'masuk' ? '+' : '−' }}Rp {{ number_format($t->amount, 0, ',', '.') }}
                    </strong>
                </div>
            @empty
                <p class="text-mute">Belum ada transaksi.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>