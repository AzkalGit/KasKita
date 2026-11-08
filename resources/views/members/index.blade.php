<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Anggota · {{ $organization->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded">{{ session('status') }}</div>
            @endif
            @if ($errors->has('member'))
                <div class="p-4 bg-red-50 text-red-800 rounded">{{ $errors->first('member') }}</div>
            @endif

            {{-- Tambah kontak --}}
            <form method="POST" action="{{ route('members.store', $organization) }}"
                  class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                @csrf
                <h3 class="font-semibold">Tambah kontak yang ditagih</h3>
                <p class="text-sm text-gray-500">
                    Orang ini belum perlu punya akun. Kalau nanti mendaftar dan memverifikasi email yang sama, akunnya otomatis tertaut.
                </p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="name" value="Nama" />
                        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email (opsional)" />
                        <x-text-input id="email" name="email" type="email" class="block mt-1 w-full" :value="old('email')" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Telepon (opsional)" />
                        <x-text-input id="phone" name="phone" class="block mt-1 w-full" :value="old('phone')" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_billable" value="0">
                    <input type="checkbox" name="is_billable" value="1" checked class="rounded border-gray-300">
                    Ditagih iuran
                </label>
                <div><x-primary-button>Tambah</x-primary-button></div>
            </form>

            {{-- Daftar anggota --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Daftar anggota ({{ $members->count() }})</h3>

                @foreach ($members as $m)
                    @php
                        $isMe = $m->user_id === auth()->id();
                        $isAdminRow = $m->role === 'admin';
                    @endphp
                    <div class="border-b py-4 last:border-0 {{ $m->is_active ? '' : 'opacity-60' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">
                                    {{ $m->name }}
                                    @if ($isMe) <span class="text-xs text-gray-500">(kamu)</span> @endif
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $m->email ?? 'tanpa email' }}@if ($m->phone) · {{ $m->phone }} @endif
                                </div>
                                <div class="text-sm text-gray-500">
                                    @if (! $m->is_active)
                                        <span class="text-red-600">Nonaktif</span>
                                    @elseif ($m->hasAccount())
                                        Peran: {{ ucfirst($m->role) }}
                                    @else
                                        Kontak (belum punya akun)
                                    @endif
                                    · {{ $m->is_billable ? 'Ditagih iuran' : 'Tidak ditagih' }}
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($m->is_active && $m->hasAccount() && ! $isAdminRow && ! $isMe)
                                    <form method="POST" action="{{ route('members.role', [$organization, $m]) }}">
                                        @csrf @method('PATCH')
                                        <select name="role" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                                            <option value="anggota" @selected($m->role === 'anggota')>Anggota</option>
                                            <option value="editor" @selected($m->role === 'editor')>Editor</option>
                                        </select>
                                    </form>
                                @endif

                                @if (! $m->is_active)
                                    <form method="POST" action="{{ route('members.reactivate', [$organization, $m]) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-sm text-indigo-600 font-medium">Aktifkan</button>
                                    </form>
                                @elseif (! $isAdminRow && ! $isMe)
                                    <form method="POST" action="{{ route('members.deactivate', [$organization, $m]) }}"
                                          onsubmit="return confirm('Nonaktifkan {{ $m->name }}? Riwayat tagihannya tetap tersimpan.')">
                                        @csrf @method('PATCH')
                                        <button class="text-sm text-red-600 font-medium">Nonaktifkan</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Ubah data --}}
                        <details class="mt-3">
                            <summary class="text-sm text-indigo-600 cursor-pointer">Ubah data</summary>
                            <form method="POST" action="{{ route('members.update', [$organization, $m]) }}"
                                  class="mt-3 grid md:grid-cols-4 gap-3 items-end">
                                @csrf @method('PATCH')
                                <div>
                                    <x-input-label value="Nama" />
                                    <x-text-input name="name" class="block mt-1 w-full" :value="$m->name" required />
                                </div>
                                <div>
                                    <x-input-label value="Email" />
                                    @if ($m->hasAccount())
                                        <div class="mt-1 text-sm text-gray-500 py-2">{{ $m->email }} (mengikuti akun)</div>
                                    @else
                                        <x-text-input name="email" type="email" class="block mt-1 w-full" :value="$m->email" />
                                    @endif
                                </div>
                                <div>
                                    <x-input-label value="Telepon" />
                                    <x-text-input name="phone" class="block mt-1 w-full" :value="$m->phone" />
                                </div>
                                <div class="flex items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input type="hidden" name="is_billable" value="0">
                                        <input type="checkbox" name="is_billable" value="1" @checked($m->is_billable) class="rounded border-gray-300">
                                        Ditagih
                                    </label>
                                    <x-primary-button>Simpan</x-primary-button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>

            <div class="text-sm space-x-4">
                <a href="{{ route('organizations.dashboard', $organization) }}" class="text-indigo-600">← Dashboard</a>
                <a href="{{ route('invitations.index', $organization) }}" class="text-indigo-600">Kelola undangan</a>
            </div>
        </div>
    </div>
</x-app-layout>