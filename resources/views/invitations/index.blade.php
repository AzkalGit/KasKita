@php
    $label = [
        'aktif' => ['Aktif', 'green'],
        'ditutup' => ['Ditutup', 'gray'],
        'kedaluwarsa' => ['Kedaluwarsa', 'red'],
    ];
    $tombol = 'inline-flex items-center rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-bold text-ink transition hover:bg-soft';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Undangan</h1>
        <p class="mt-0.5 text-mute">Kode untuk mengajak anggota bergabung ke {{ $organization->name }}.</p>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div role="status" class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">
                @foreach ($errors->all() as $pesan)
                    <div>{{ $pesan }}</div>
                @endforeach
            </div>
        @endif

        {{-- Buat kode baru --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="text-base font-bold">Buat kode baru</h3>
            <p class="mt-1 text-mute">
                Bagikan kode ke calon anggota. Kode <strong>hanya</strong> memberi peran Anggota; untuk menjadikan seseorang
                Editor atau Admin, ubah perannya setelah ia bergabung. Tutup atau ganti kode kapan saja bila perlu.
            </p>

            <form method="POST" action="{{ route('invitations.store', $organization) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <x-input-label for="days" value="Masa berlaku" />
                    <x-select-input id="days" name="days" class="mt-1 block">
                        @foreach ($durations as $hari)
                            <option value="{{ $hari }}" @selected((int) old('days', 7) === $hari)>{{ $hari }} hari</option>
                        @endforeach
                    </x-select-input>
                </div>
                <x-primary-button>Buat kode</x-primary-button>
            </form>
        </div>

        {{-- Daftar kode --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="text-base font-bold">Kode undangan</h3>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[720px] text-left">
                    <thead>
                        <tr class="border-b border-line text-xs font-bold uppercase tracking-wider text-mute">
                            <th class="py-2.5 pr-4">Kode</th>
                            <th class="py-2.5 pr-4">Status</th>
                            <th class="py-2.5 pr-4">Berlaku sampai</th>
                            <th class="py-2.5 pr-4">Dipakai</th>
                            <th class="py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invitations as $inv)
                            @php $status = $inv->status(); @endphp
                            <tr class="border-b border-line last:border-0">
                                <td class="py-3 pr-4">
                                    <code class="font-mono text-base font-bold tracking-widest">{{ $inv->code }}</code>
                                </td>
                                <td class="py-3 pr-4">
                                    <x-badge :tone="$label[$status][1]">{{ $label[$status][0] }}</x-badge>
                                </td>
                                <td class="py-3 pr-4 text-mute">
                                    {{ $inv->expires_at ? $inv->expires_at->format('d/m/Y H:i') : 'Tanpa batas waktu' }}
                                </td>
                                <td class="py-3 pr-4 text-mute">{{ $inv->uses_count }} orang</td>
                                <td class="py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($status === 'aktif')
                                            <button type="button" class="{{ $tombol }}" x-data="{ ok: false }"
                                                    x-on:click="navigator.clipboard.writeText('{{ $inv->code }}').then(() => { ok = true; setTimeout(() => ok = false, 1500) })">
                                                <span x-text="ok ? 'Tersalin' : 'Salin'">Salin</span>
                                            </button>
                                        @endif

                                        @if ($status === 'aktif')
                                            <form method="POST" action="{{ route('invitations.close', [$organization, $inv]) }}">
                                                @csrf @method('PATCH')
                                                <button class="{{ $tombol }}">Tutup</button>
                                            </form>
                                        @elseif ($status === 'ditutup')
                                            <form method="POST" action="{{ route('invitations.reopen', [$organization, $inv]) }}">
                                                @csrf @method('PATCH')
                                                <button class="{{ $tombol }}">Buka</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('invitations.regenerate', [$organization, $inv]) }}" class="flex items-center gap-1">
                                            @csrf
                                            <x-select-input name="days" aria-label="Masa berlaku kode pengganti" class="!py-1 text-xs">
                                                @foreach ($durations as $hari)
                                                    <option value="{{ $hari }}" @selected($hari === 7)>{{ $hari }} hari</option>
                                                @endforeach
                                            </x-select-input>
                                            <button class="{{ $tombol }}">Ganti kode</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-mute">Belum ada kode. Buat kode baru di atas, lalu bagikan ke calon anggota.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Siapa yang bergabung --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="text-base font-bold">Bergabung lewat kode</h3>

            <div class="mt-2">
                @forelse ($joins as $row)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-3 last:border-0">
                        <div>
                            <div class="font-semibold">{{ $row['use']->user->name }}</div>
                            <div class="text-xs text-mute">{{ $row['use']->user->email }}</div>
                        </div>
                        <div class="text-right text-xs text-mute">
                            <div><code class="font-mono tracking-widest">{{ $row['code'] }}</code></div>
                            <div>{{ $row['use']->used_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-mute">Belum ada yang bergabung lewat kode.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>