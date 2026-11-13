@php
    $btnPrimary = 'inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark';
    $btnGhost = 'inline-flex items-center justify-center rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-soft';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Laporan</h1>
        <p class="mt-0.5 text-mute">Ringkasan kas {{ $organization->name }} per bulan atau per minggu.</p>
    </x-slot>

    <div class="space-y-4">
        @if ($errors->any())
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Filter --}}
        <form method="GET" action="{{ route('reports.index', $organization) }}"
              class="flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-white p-5">
            <div>
                <x-input-label for="month" value="Bulan" />
                <x-text-input type="month" id="month" name="month" :value="$month" class="mt-1 block" />
            </div>
            <div>
                <x-input-label for="week" value="Minggu" />
                <x-select-input id="week" name="week" class="mt-1 block">
                    <option value="">Satu bulan penuh</option>
                    @foreach ($weeks as $n => $text)
                        <option value="{{ $n }}" @selected($week === $n)>{{ $text }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <button type="submit" class="{{ $btnPrimary }}">Tampilkan</button>
            <a href="{{ route('reports.download', [$organization] + array_filter(['month' => $month, 'week' => $week])) }}"
               class="{{ $btnGhost }}">Unduh CSV</a>
        </form>

        <div class="text-mute">Periode: <span class="font-bold text-ink">{{ $label }}</span></div>

        {{-- Status tutup buku untuk bulan yang dipilih --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white p-4">
            @if ($closure)
                <div>
                    <x-badge tone="gray">Buku ditutup</x-badge>
                    <span class="ml-2 text-mute">
                        {{ \App\Models\Invoice::labelFor($month) }} ditutup {{ $closure->closed_at->format('d/m/Y H:i') }}@if ($closure->closer) oleh {{ $closure->closer->name }}@endif.
                        Transaksi bulan ini tidak bisa ditambah, diubah, atau dihapus.
                    </span>
                </div>
                @if ($canClose)
                    <form method="POST" action="{{ route('periods.reopen', [$organization, $month]) }}"
                          onsubmit="return confirm('Buka kembali buku bulan ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="{{ $btnGhost }} !py-1.5">Buka kembali</button>
                    </form>
                @endif
            @else
                <div class="text-mute">Buku {{ \App\Models\Invoice::labelFor($month) }} masih terbuka.</div>
                @if ($canClose && $isPastMonth)
                    <form method="POST" action="{{ route('periods.close', $organization) }}"
                          onsubmit="return confirm('Tutup buku bulan ini? Transaksi bulan ini tidak bisa diubah lagi sampai dibuka kembali.')">
                        @csrf
                        <input type="hidden" name="period" value="{{ $month }}">
                        <button type="submit" class="{{ $btnPrimary }} !py-1.5">Tutup buku</button>
                    </form>
                @elseif ($canClose)
                    <span class="text-mute">Bulan berjalan belum bisa ditutup.</span>
                @endif
            @endif
        </div>

        {{-- Ringkasan --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="font-semibold text-mute">Saldo awal</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight">Rp {{ number_format($opening, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="font-semibold text-mute">Pemasukan</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-income">Rp {{ number_format($masuk, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="font-semibold text-mute">Pengeluaran</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-expense">Rp {{ number_format($keluar, 0, ',', '.') }}</div>
            </div>
            <div class="rounded-2xl bg-deep p-5 text-white">
                <div class="font-semibold text-[#9ccfc9]">Saldo akhir</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight">Rp {{ number_format($closing, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Rincian per kategori --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-3 text-base font-bold">Rincian per kategori</h3>
            @if ($breakdown->isEmpty())
                <p class="text-mute">Tidak ada transaksi pada periode ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left">
                        <thead>
                            <tr class="border-b border-line text-xs font-bold uppercase tracking-wider text-mute">
                                <th class="py-2.5 pr-3">Kategori</th>
                                <th class="py-2.5 pr-3 text-right">Pemasukan</th>
                                <th class="py-2.5 text-right">Pengeluaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdown as $row)
                                <tr class="border-b border-line last:border-0">
                                    <td class="py-2.5 pr-3">{{ $row['name'] }}</td>
                                    <td class="whitespace-nowrap py-2.5 pr-3 text-right">Rp {{ number_format($row['masuk'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap py-2.5 text-right">Rp {{ number_format($row['keluar'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Daftar transaksi --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-3 text-base font-bold">Transaksi pada periode ini</h3>
            @forelse ($transactions as $t)
                <div class="flex justify-between gap-3 border-b border-line py-2.5 last:border-0">
                    <div class="min-w-0">
                        <div class="truncate">{{ $t->description }}</div>
                        <div class="text-xs text-mute">{{ $t->tanggal->format('d/m/Y') }} · {{ $t->category?->name }}</div>
                    </div>
                    <strong class="whitespace-nowrap {{ $t->type === 'masuk' ? 'text-income' : 'text-expense' }}">
                        {{ $t->type === 'masuk' ? '+' : '−' }}Rp {{ number_format($t->amount, 0, ',', '.') }}
                    </strong>
                </div>
            @empty
                <p class="text-mute">Tidak ada transaksi pada periode ini.</p>
            @endforelse
        </div>

        {{-- Tunggakan: hanya dirender bila datanya dikirim (Admin) --}}
        @if ($arrears !== null)
            <div class="rounded-2xl border border-line bg-white p-5">
                <h3 class="text-base font-bold">Tunggakan iuran</h3>
                <p class="mb-3 text-mute">Semua tagihan yang belum lunas per hari ini (tidak mengikuti filter periode).</p>
                @forelse ($arrears as $inv)
                    <div class="flex justify-between gap-3 border-b border-line py-2.5 last:border-0">
                        <div>
                            <div class="font-semibold">{{ $inv->member->name }}</div>
                            <div class="text-xs text-mute">
                                {{ $inv->periodLabel() }} ·
                                {{ $inv->status === 'menunggu' ? 'Menunggu verifikasi' : 'Belum bayar' }}
                            </div>
                        </div>
                        <div class="whitespace-nowrap font-bold">Rp {{ number_format($inv->amount, 0, ',', '.') }}</div>
                    </div>
                @empty
                    <p class="text-mute">Tidak ada tunggakan.</p>
                @endforelse
                @if ($arrears->isNotEmpty())
                    <div class="flex justify-between pt-3 font-extrabold">
                        <span>Total</span>
                        <span>Rp {{ number_format($arrears->sum('amount'), 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>