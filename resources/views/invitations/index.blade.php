@php
    $label = [
        'aktif' => ['Aktif', 'bg-green-100 text-green-800'],
        'ditutup' => ['Ditutup', 'bg-gray-100 text-gray-700'],
        'kedaluwarsa' => ['Kedaluwarsa', 'bg-red-100 text-red-700'],
    ];
    $tombol = 'inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50';
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Undangan · {{ $organization->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('organizations.dashboard', $organization) }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke dashboard</a>

            @if (session('status'))
                <div role="status" class="p-4 rounded-lg bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="p-4 rounded-lg bg-red-50 text-red-800 text-sm">
                    @foreach ($errors->all() as $pesan)
                        <div>{{ $pesan }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Buat kode baru --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Buat kode baru</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Bagikan kode ke calon anggota. Kode <strong>hanya</strong> memberi peran Anggota; untuk menjadikan seseorang
                    Editor atau Admin, ubah perannya setelah ia bergabung. Tutup atau ganti kode kapan saja bila perlu.
                </p>

                <form method="POST" action="{{ route('invitations.store', $organization) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="days" value="Masa berlaku" />
                        <select id="days" name="days" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm">
                            @foreach ($durations as $hari)
                                <option value="{{ $hari }}" @selected((int) old('days', 7) === $hari)>{{ $hari }} hari</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Buat kode</x-primary-button>
                </form>
            </div>

            {{-- Daftar kode --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Kode undangan</h3>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="py-2 pr-4 font-medium">Kode</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Berlaku sampai</th>
                                <th class="py-2 pr-4 font-medium">Dipakai</th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($invitations as $inv)
                                @php $status = $inv->status(); @endphp
                                <tr>
                                    <td class="py-3 pr-4">
                                        <code class="font-mono text-base font-semibold tracking-widest text-gray-900">{{ $inv->code }}</code>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="px-2 py-1 rounded text-xs font-semibold {{ $label[$status][1] }}">{{ $label[$status][0] }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700">
                                        {{ $inv->expires_at ? $inv->expires_at->format('d/m/Y H:i') : 'Tanpa batas waktu' }}
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700">{{ $inv->uses_count }} orang</td>
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
                                                <select name="days" aria-label="Masa berlaku kode pengganti" class="border-gray-300 rounded-md text-xs py-1">
                                                    @foreach ($durations as $hari)
                                                        <option value="{{ $hari }}" @selected($hari === 7)>{{ $hari }} hari</option>
                                                    @endforeach
                                                </select>
                                                <button class="{{ $tombol }}">Ganti kode</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-600">Belum ada kode. Buat kode baru di atas, lalu bagikan ke calon anggota.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Siapa yang bergabung --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Bergabung lewat kode</h3>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($joins as $row)
                        <div class="py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <div class="font-medium text-gray-900">{{ $row['use']->user->name }}</div>
                                <div class="text-gray-500">{{ $row['use']->user->email }}</div>
                            </div>
                            <div class="text-right text-gray-500">
                                <div><code class="font-mono tracking-widest">{{ $row['code'] }}</code></div>
                                <div>{{ $row['use']->used_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-gray-600">Belum ada yang bergabung lewat kode.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
