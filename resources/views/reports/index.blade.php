<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Laporan · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 text-red-700 p-4 sm:rounded-lg text-sm">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Filter --}}
            <form method="GET" action="{{ route('reports.index', $organization) }}"
                  class="bg-white shadow sm:rounded-lg p-6 flex flex-wrap items-end gap-3">
                <div>
                    <label for="month" class="block text-sm text-gray-600">Bulan</label>
                    <input type="month" id="month" name="month" value="{{ $month }}"
                           class="border-gray-300 rounded-md shadow-sm text-sm">
                </div>
                <div>
                    <label for="week" class="block text-sm text-gray-600">Minggu</label>
                    <select id="week" name="week" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">Satu bulan penuh</option>
                        @foreach ($weeks as $n => $text)
                            <option value="{{ $n }}" @selected($week === $n)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700">
                    Tampilkan
                </button>
                <a href="{{ route('reports.download', [$organization] + array_filter(['month' => $month, 'week' => $week])) }}"
                   class="px-4 py-2 border border-indigo-600 text-indigo-600 rounded-md text-sm font-semibold hover:bg-indigo-50">
                    Unduh CSV
                </a>
            </form>

            <div class="text-sm text-gray-600">Periode: <span class="font-semibold">{{ $label }}</span></div>

            {{-- Ringkasan --}}
            <div class="grid md:grid-cols-4 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Saldo awal</div>
                    <div class="text-xl font-semibold">Rp {{ number_format($opening, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Pemasukan</div>
                    <div class="text-xl font-semibold text-green-700">Rp {{ number_format($masuk, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Pengeluaran</div>
                    <div class="text-xl font-semibold text-red-700">Rp {{ number_format($keluar, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Saldo akhir</div>
                    <div class="text-xl font-semibold">Rp {{ number_format($closing, 0, ',', '.') }}</div>
                </div>
            </div>

            {{-- Rincian per kategori --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Rincian per kategori</h3>
                @if ($breakdown->isEmpty())
                    <p class="text-gray-500">Tidak ada transaksi pada periode ini.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="py-2">Kategori</th>
                                    <th class="py-2 text-right">Pemasukan</th>
                                    <th class="py-2 text-right">Pengeluaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($breakdown as $row)
                                    <tr class="border-b last:border-0">
                                        <td class="py-2">{{ $row['name'] }}</td>
                                        <td class="py-2 text-right">Rp {{ number_format($row['masuk'], 0, ',', '.') }}</td>
                                        <td class="py-2 text-right">Rp {{ number_format($row['keluar'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Daftar transaksi --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Transaksi pada periode ini</h3>
                @forelse ($transactions as $t)
                    <div class="flex justify-between border-b py-2 last:border-0">
                        <div>
                            <div>{{ $t->description }}</div>
                            <div class="text-sm text-gray-500">{{ $t->tanggal->format('d/m/Y') }} · {{ $t->category?->name }}</div>
                        </div>
                        <div class="{{ $t->type === 'masuk' ? 'text-green-700' : 'text-red-700' }}">
                            {{ $t->type === 'masuk' ? '+' : '−' }} Rp {{ number_format($t->amount, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500">Tidak ada transaksi pada periode ini.</p>
                @endforelse
            </div>

            {{-- Tunggakan: hanya dirender bila datanya dikirim (Admin) --}}
            @if ($arrears !== null)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="font-semibold mb-1">Tunggakan iuran</h3>
                    <p class="text-sm text-gray-500 mb-4">Semua tagihan yang belum lunas per hari ini (tidak mengikuti filter periode).</p>
                    @forelse ($arrears as $inv)
                        <div class="flex justify-between border-b py-2 last:border-0">
                            <div>
                                <div>{{ $inv->member->name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $inv->periodLabel() }} ·
                                    {{ $inv->status === 'menunggu' ? 'Menunggu verifikasi' : 'Belum bayar' }}
                                </div>
                            </div>
                            <div>Rp {{ number_format($inv->amount, 0, ',', '.') }}</div>
                        </div>
                    @empty
                        <p class="text-gray-500">Tidak ada tunggakan.</p>
                    @endforelse
                    @if ($arrears->isNotEmpty())
                        <div class="flex justify-between pt-3 font-semibold">
                            <span>Total</span>
                            <span>Rp {{ number_format($arrears->sum('amount'), 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <a href="{{ route('organizations.dashboard', $organization) }}" class="text-sm text-indigo-600">← Kembali ke dashboard</a>
        </div>
    </div>
</x-app-layout>