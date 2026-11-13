@php
    $badge = [
        'lunas' => ['Lunas', 'green'],
        'menunggu' => ['Menunggu verifikasi', 'yellow'],
        'belum' => ['Belum dibayar', 'red'],
    ];
    $methodLabel = ['transfer' => 'Transfer', 'tunai' => 'Tunai'];
    $fileClass = 'text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-soft file:px-3 file:py-2 file:font-semibold file:text-deep';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Iuran</h1>
        <p class="mt-0.5 text-mute">Tagihan iuran {{ $organization->name }}.</p>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @if ($errors->hasAny(['invoice', 'proof', 'method', 'reason']))
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">
                {{ $errors->first('invoice') ?: ($errors->first('proof') ?: ($errors->first('method') ?: $errors->first('reason'))) }}
            </div>
        @endif

        @if ($canManage)
            {{-- Buat tagihan bulanan (Admin dan Editor) --}}
            <form method="POST" action="{{ route('invoices.store', $organization) }}"
                  class="grid items-end gap-4 rounded-2xl border border-line bg-white p-5 md:grid-cols-3">
                @csrf
                <div class="md:col-span-3">
                    <h3 class="text-base font-bold">Buat tagihan bulanan</h3>
                    <p class="text-mute">
                        Tagihan dibuat untuk semua anggota aktif yang ditagih. Anggota yang sudah punya tagihan bulan itu dilewati.
                    </p>
                </div>
                <div>
                    <x-input-label for="period" value="Bulan" />
                    <x-text-input id="period" name="period" type="month" class="mt-1 block w-full"
                                  :value="old('period', now()->format('Y-m'))" required />
                    <x-input-error :messages="$errors->get('period')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="amount" value="Jumlah per anggota (Rp, tanpa titik atau koma)" />
                    <x-text-input id="amount" name="amount" type="number" min="1" step="1" class="mt-1 block w-full"
                                  :value="old('amount', $organization->default_dues ?: '')" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div><x-primary-button>Buat tagihan</x-primary-button></div>
            </form>

            {{-- Pilih bulan yang dilihat --}}
            <form method="GET" action="{{ route('invoices.index', $organization) }}"
                  class="flex flex-wrap items-end gap-4 rounded-2xl border border-line bg-white p-5">
                <div>
                    <x-input-label for="f_period" value="Lihat bulan" />
                    <x-text-input id="f_period" name="period" type="month" class="mt-1 block"
                                  :value="$period ?? ''" />
                </div>
                <x-primary-button>Terapkan</x-primary-button>
                <a href="{{ route('invoices.index', $organization) }}" class="py-2.5 text-sm font-semibold text-mute hover:text-ink">Bulan terbaru</a>
            </form>

            @if ($period)
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-line bg-white p-5">
                        <div class="font-semibold text-mute">Lunas ({{ $stats['lunas']['jumlah'] }})</div>
                        <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-income">Rp {{ number_format($stats['lunas']['nominal'], 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-line bg-white p-5">
                        <div class="font-semibold text-mute">Menunggu verifikasi ({{ $stats['menunggu']['jumlah'] }})</div>
                        <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-[#7a5600]">Rp {{ number_format($stats['menunggu']['nominal'], 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl border border-line bg-white p-5">
                        <div class="font-semibold text-mute">Belum dibayar ({{ $stats['belum']['jumlah'] }})</div>
                        <div class="mt-1.5 text-[22px] font-extrabold tracking-tight text-expense">Rp {{ number_format($stats['belum']['nominal'], 0, ',', '.') }}</div>
                    </div>
                </div>
            @endif
        @else
            {{-- Anggota: cara membayar --}}
            @if ($organization->payment_info)
                <div class="rounded-2xl border border-line bg-white p-5">
                    <h3 class="text-base font-bold">Cara membayar</h3>
                    <p class="mt-1">{{ $organization->payment_info }}</p>
                    <p class="mt-2 text-mute">Setelah membayar, tekan "Saya sudah bayar" pada tagihanmu di bawah. Admin atau Editor akan memeriksanya.</p>
                </div>
            @endif
        @endif

        {{-- Daftar tagihan --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-4 text-base font-bold">
                @if ($canManage)
                    Tagihan {{ $period ? \App\Models\Invoice::labelFor($period) : '' }} ({{ $invoices->total() }})
                @else
                    Tagihan saya ({{ $invoices->total() }})
                @endif
            </h3>

            @forelse ($invoices as $inv)
                @php
                    // Pemilik tagihan = akun yang sedang login (Anggota biasa, atau Admin/Editor yang ditagih)
                    $isOwn = $inv->member->user_id !== null
                        && (int) $inv->member->user_id === (int) auth()->id();
                @endphp
                <div class="border-b border-line py-3.5 last:border-0">
                    <div class="flex justify-between gap-4">
                        <div class="min-w-0">
                            @if ($canManage)
                                <div class="font-semibold">
                                    {{ $inv->member->name }}
                                    @if ($isOwn) <span class="text-xs font-normal text-mute">(tagihanmu)</span> @endif
                                </div>
                                <div class="text-xs text-mute">
                                    {{ $inv->periodLabel() }}
                                    @unless ($inv->member->hasAccount())
                                        · <span>belum punya akun</span>
                                    @endunless
                                </div>
                            @else
                                <div class="font-semibold">Iuran {{ $inv->periodLabel() }}</div>
                                @if ($inv->paid_at)
                                    <div class="text-xs text-mute">Dibayar {{ $inv->paid_at->format('d/m/Y') }}</div>
                                @endif
                            @endif
                        </div>
                        <div class="whitespace-nowrap text-right">
                            <div class="font-bold">Rp {{ number_format($inv->amount, 0, ',', '.') }}</div>
                            <x-badge :tone="$badge[$inv->status][1] ?? 'gray'" class="mt-1">{{ $badge[$inv->status][0] ?? $inv->status }}</x-badge>
                        </div>
                    </div>

                    {{-- Pengelola: verifikasi tagihan orang lain --}}
                    @if ($canManage)
                        @if ($isOwn)
                            <div class="mt-2 text-mute">
                                @if ($inv->isPaid())
                                    Pelunasan tagihanmu sendiri hanya bisa dibatalkan oleh pengelola lain.
                                @elseif (! $hasOtherManager)
                                    Belum ada pengelola lain (Admin atau Editor) yang bisa memverifikasi tagihan ini.
                                    Jadikan seorang anggota sebagai Editor di halaman Anggota.
                                @elseif ($inv->status === 'menunggu')
                                    Menunggu diverifikasi pengelola lain.
                                @else
                                    Pengelola lain bisa melunasi tagihan ini secara tunai.
                                @endif
                            </div>
                        @else
                            @if ($inv->status === 'menunggu')
                                <div class="mt-2">
                                    Diajukan lewat <strong>{{ $methodLabel[$inv->method] ?? $inv->method }}</strong> ·
                                    @include('invoices._proof')
                                </div>
                            @elseif ($inv->status === 'lunas' && $inv->proof_path)
                                <div class="mt-2">@include('invoices._proof')</div>
                            @elseif ($inv->status === 'belum' && $inv->rejection_reason)
                                <div class="mt-2 text-expense">Pengajuan sebelumnya ditolak: {{ $inv->rejection_reason }}</div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-start gap-4 font-semibold">
                                @if ($inv->status === 'menunggu')
                                    <form method="POST" action="{{ route('invoices.approve', [$organization, $inv->id]) }}"
                                          onsubmit="return confirm({{ Js::from('Setujui pengajuan iuran '.$inv->member->name.' dan catat sebagai lunas?') }})">
                                        @csrf @method('PATCH')
                                        <button class="text-brand hover:text-brand-dark">Setujui</button>
                                    </form>
                                    <form method="POST" action="{{ route('invoices.reject', [$organization, $inv->id]) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <x-text-input type="text" name="reason" maxlength="255" placeholder="Alasan penolakan" required
                                                      class="!py-1.5 text-sm" />
                                        <button class="text-expense hover:opacity-80">Tolak</button>
                                    </form>
                                @endif

                                @if (! $inv->isPaid())
                                    <form method="POST" action="{{ route('invoices.pay_cash', [$organization, $inv->id]) }}"
                                          onsubmit="return confirm({{ Js::from('Catat iuran '.$inv->member->name.' sebagai lunas tunai?') }})">
                                        @csrf @method('PATCH')
                                        <button class="text-brand hover:text-brand-dark">Lunas tunai</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('invoices.cancel', [$organization, $inv->id]) }}"
                                          onsubmit="return confirm({{ Js::from('Batalkan pelunasan iuran '.$inv->member->name.'? Transaksi otomatisnya akan dihapus.') }})">
                                        @csrf @method('PATCH')
                                        <button class="text-expense hover:opacity-80">Batalkan pelunasan</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Pemilik tagihan: ajukan pembayaran dan urus bukti --}}
                    @if ($isOwn)
                        @if ($inv->status === 'belum')
                            @if ($inv->rejection_reason)
                                <div class="mt-3 text-expense">
                                    Pengajuanmu ditolak: {{ $inv->rejection_reason }}. Kamu bisa mengajukan ulang.
                                </div>
                            @endif
                            <form method="POST" action="{{ route('invoices.submit', [$organization, $inv->id]) }}"
                                  enctype="multipart/form-data"
                                  class="mt-3 flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <label for="method_{{ $inv->id }}" class="block text-mute">Cara bayar</label>
                                    <x-select-input id="method_{{ $inv->id }}" name="method" class="mt-1 !py-2 text-sm">
                                        <option value="transfer">Transfer</option>
                                        <option value="tunai">Tunai</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <label for="proof_{{ $inv->id }}" class="block text-mute">Bukti (opsional; JPG, PNG, atau PDF, maks. 2 MB)</label>
                                    <input id="proof_{{ $inv->id }}" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 {{ $fileClass }}">
                                </div>
                                <x-primary-button>Saya sudah bayar</x-primary-button>
                            </form>
                        @elseif ($inv->status === 'menunggu')
                            <div class="mt-3">
                                Pengajuanmu ({{ $methodLabel[$inv->method] ?? $inv->method }}) menunggu diperiksa pengelola ·
                                @include('invoices._proof')
                            </div>
                            <div class="mt-2 flex flex-wrap items-end gap-4 font-semibold">
                                <form method="POST" action="{{ route('invoices.proof_store', [$organization, $inv->id]) }}"
                                      enctype="multipart/form-data" class="flex items-center gap-2">
                                    @csrf
                                    <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required class="{{ $fileClass }}">
                                    <button class="text-brand hover:text-brand-dark">{{ $inv->proof_path ? 'Ganti bukti' : 'Tambah bukti' }}</button>
                                </form>
                                @if ($inv->proof_path)
                                    <form method="POST" action="{{ route('invoices.proof_destroy', [$organization, $inv->id]) }}"
                                          onsubmit="return confirm('Hapus bukti pembayaran ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-expense hover:opacity-80">Hapus bukti</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('invoices.withdraw', [$organization, $inv->id]) }}"
                                      onsubmit="return confirm('Tarik pengajuan ini? Buktinya ikut dihapus.')">
                                    @csrf @method('PATCH')
                                    <button class="text-mute hover:text-ink">Tarik pengajuan</button>
                                </form>
                            </div>
                        @elseif ($inv->proof_path)
                            <div class="mt-3">@include('invoices._proof')</div>
                        @endif
                    @endif
                </div>
            @empty
                <p class="text-mute">
                    @if ($canManage)
                        Belum ada tagihan. Buat tagihan bulan ini di atas.
                    @else
                        Belum ada tagihan untukmu.
                    @endif
                </p>
            @endforelse

            <div class="mt-4">{{ $invoices->links() }}</div>
        </div>
    </div>
</x-app-layout>