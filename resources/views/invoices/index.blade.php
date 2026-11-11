@php
    $badge = [
        'lunas' => ['Lunas', 'bg-green-100 text-green-700'],
        'menunggu' => ['Menunggu verifikasi', 'bg-yellow-100 text-yellow-800'],
        'belum' => ['Belum dibayar', 'bg-red-100 text-red-700'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Iuran · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->has('invoice'))
                <div class="p-4 bg-red-50 text-red-800 rounded">{{ $errors->first('invoice') }}</div>
            @endif

            @if ($canManage)
                {{-- Buat tagihan bulanan (Admin dan Editor) --}}
                <form method="POST" action="{{ route('invoices.store', $organization) }}"
                      class="bg-white shadow sm:rounded-lg p-6 grid md:grid-cols-3 gap-4 items-end">
                    @csrf
                    <div class="md:col-span-3">
                        <h3 class="font-semibold">Buat tagihan bulanan</h3>
                        <p class="text-sm text-gray-500">
                            Tagihan dibuat untuk semua anggota aktif yang ditagih. Anggota yang sudah punya tagihan bulan itu dilewati.
                        </p>
                    </div>
                    <div>
                        <x-input-label for="period" value="Bulan" />
                        <x-text-input id="period" name="period" type="month" class="block mt-1 w-full"
                                      :value="old('period', now()->format('Y-m'))" required />
                        <x-input-error :messages="$errors->get('period')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Jumlah per anggota (Rp, tanpa titik atau koma)" />
                        <x-text-input id="amount" name="amount" type="number" min="1" step="1" class="block mt-1 w-full"
                                      :value="old('amount', $organization->default_dues ?: '')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div><x-primary-button>Buat tagihan</x-primary-button></div>
                </form>

                {{-- Pilih bulan yang dilihat --}}
                <form method="GET" action="{{ route('invoices.index', $organization) }}"
                      class="bg-white shadow sm:rounded-lg p-6 flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="f_period" value="Lihat bulan" />
                        <x-text-input id="f_period" name="period" type="month" class="block mt-1"
                                      :value="$period ?? ''" />
                    </div>
                    <x-primary-button>Terapkan</x-primary-button>
                    <a href="{{ route('invoices.index', $organization) }}" class="text-sm text-gray-600">Bulan terbaru</a>
                </form>

                @if ($period)
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <div class="text-sm text-gray-500">Lunas ({{ $stats['lunas']['jumlah'] }})</div>
                            <div class="text-xl font-semibold text-green-700">Rp {{ number_format($stats['lunas']['nominal'], 0, ',', '.') }}</div>
                        </div>
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <div class="text-sm text-gray-500">Menunggu verifikasi ({{ $stats['menunggu']['jumlah'] }})</div>
                            <div class="text-xl font-semibold text-yellow-700">Rp {{ number_format($stats['menunggu']['nominal'], 0, ',', '.') }}</div>
                        </div>
                        <div class="bg-white shadow sm:rounded-lg p-6">
                            <div class="text-sm text-gray-500">Belum dibayar ({{ $stats['belum']['jumlah'] }})</div>
                            <div class="text-xl font-semibold text-red-700">Rp {{ number_format($stats['belum']['nominal'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endif
            @else
                {{-- Anggota: cara membayar --}}
                @if ($organization->payment_info)
                    <div class="bg-white shadow sm:rounded-lg p-6">
                        <h3 class="font-semibold">Cara membayar</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ $organization->payment_info }}</p>
                        <p class="text-sm text-gray-500 mt-2">Untuk saat ini pelunasan dicatat oleh Admin atau Editor setelah pembayaran diterima.</p>
                    </div>
                @endif
            @endif

            {{-- Daftar tagihan --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="font-semibold mb-4">
                    @if ($canManage)
                        Tagihan {{ $period ? \App\Models\Invoice::labelFor($period) : '' }} ({{ $invoices->total() }})
                    @else
                        Tagihan saya ({{ $invoices->total() }})
                    @endif
                </div>

                @forelse ($invoices as $inv)
                    @php
                        // E2: pengelola tidak boleh menyentuh tagihannya sendiri
                        $isOwn = $canManage
                            && $inv->member->user_id !== null
                            && (int) $inv->member->user_id === (int) auth()->id();
                    @endphp
                    <div class="border-b py-3 last:border-0">
                        <div class="flex justify-between gap-4">
                            <div>
                                @if ($canManage)
                                    <div>
                                        {{ $inv->member->name }}
                                        @if ($isOwn) <span class="text-xs text-gray-500">(tagihanmu)</span> @endif
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $inv->periodLabel() }}
                                        @unless ($inv->member->hasAccount())
                                            · <span class="text-xs">belum punya akun</span>
                                        @endunless
                                    </div>
                                @else
                                    <div>Iuran {{ $inv->periodLabel() }}</div>
                                    @if ($inv->paid_at)
                                        <div class="text-sm text-gray-500">Dibayar {{ $inv->paid_at->format('d/m/Y') }}</div>
                                    @endif
                                @endif
                            </div>
                            <div class="text-right whitespace-nowrap">
                                <div>Rp {{ number_format($inv->amount, 0, ',', '.') }}</div>
                                <span class="text-xs font-semibold px-2 py-1 rounded {{ $badge[$inv->status][1] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $badge[$inv->status][0] ?? $inv->status }}
                                </span>
                            </div>
                        </div>

                        @if ($canManage)
                            @if ($isOwn)
                                <div class="mt-2 text-sm text-gray-500">
                                    @if ($inv->isPaid())
                                        Pelunasan tagihanmu sendiri hanya bisa dibatalkan oleh pengelola lain.
                                    @elseif ($hasOtherManager)
                                        Menunggu diverifikasi pengelola lain.
                                    @else
                                        Belum ada pengelola lain (Admin atau Editor) yang bisa memverifikasi tagihan ini.
                                        Jadikan seorang anggota sebagai Editor di halaman Anggota.
                                    @endif
                                </div>
                            @else
                                <div class="mt-2 flex items-center gap-4 text-sm">
                                    @if (! $inv->isPaid())
                                        <form method="POST" action="{{ route('invoices.pay_cash', [$organization, $inv->id]) }}"
                                              onsubmit="return confirm({{ Js::from('Catat iuran '.$inv->member->name.' sebagai lunas tunai?') }})">
                                            @csrf @method('PATCH')
                                            <button class="text-indigo-600 font-medium">Lunas tunai</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('invoices.cancel', [$organization, $inv->id]) }}"
                                              onsubmit="return confirm({{ Js::from('Batalkan pelunasan iuran '.$inv->member->name.'? Transaksi otomatisnya akan dihapus.') }})">
                                            @csrf @method('PATCH')
                                            <button class="text-red-600 font-medium">Batalkan pelunasan</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @empty
                    <p class="text-gray-500">
                        @if ($canManage)
                            Belum ada tagihan. Buat tagihan bulan ini di atas.
                        @else
                            Belum ada tagihan untukmu.
                        @endif
                    </p>
                @endforelse

                <div class="mt-4">{{ $invoices->links() }}</div>
            </div>

            <a href="{{ route('organizations.dashboard', $organization) }}" class="text-sm text-indigo-600">← Dashboard</a>
        </div>
    </div>
</x-app-layout>