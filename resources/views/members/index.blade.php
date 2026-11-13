<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">Anggota</h1>
                <p class="mt-0.5 text-mute">Orang yang ditagih dan pengelola {{ $organization->name }}.</p>
            </div>
            <a href="{{ route('invitations.index', $organization) }}"
               class="rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-soft">Kelola undangan</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @if ($errors->has('member'))
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">{{ $errors->first('member') }}</div>
        @endif

        {{-- Tambah kontak --}}
        <form method="POST" action="{{ route('members.store', $organization) }}"
              class="space-y-4 rounded-2xl border border-line bg-white p-5">
            @csrf
            <div>
                <h3 class="text-base font-bold">Tambah kontak yang ditagih</h3>
                <p class="text-mute">
                    Orang ini belum perlu punya akun. Kalau nanti mendaftar dan memverifikasi email yang sama, akunnya otomatis tertaut.
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <x-input-label for="name" value="Nama" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" value="Email (opsional)" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="phone" value="Telepon (opsional)" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
            </div>
            <label class="inline-flex items-center gap-2">
                <input type="hidden" name="is_billable" value="0">
                <input type="checkbox" name="is_billable" value="1" checked class="rounded border-line text-brand focus:ring-brand">
                Ditagih iuran
            </label>
            <div><x-primary-button>Tambah</x-primary-button></div>
        </form>

        {{-- Daftar anggota --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-2 text-base font-bold">Daftar anggota ({{ $members->count() }})</h3>

            @foreach ($members as $m)
                @php
                    $isMe = $m->user_id === auth()->id();
                    $isAdminRow = $m->role === 'admin';
                @endphp
                <div class="border-b border-line py-4 last:border-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 {{ $m->is_active ? '' : 'opacity-60' }}">
                            <div class="font-semibold">
                                {{ $m->name }}
                                @if ($isMe) <span class="text-xs font-normal text-mute">(kamu)</span> @endif
                            </div>
                            <div class="text-xs text-mute">
                                {{ $m->email ?? 'tanpa email' }}@if ($m->phone) · {{ $m->phone }} @endif
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-mute">
                                @if (! $m->is_active)
                                    <x-badge tone="red">Nonaktif</x-badge>
                                @elseif ($m->hasAccount())
                                    <x-badge tone="green">Peran: {{ ucfirst($m->role) }}</x-badge>
                                @else
                                    <x-badge tone="gray">Kontak (belum punya akun)</x-badge>
                                @endif
                                <span>{{ $m->is_billable ? 'Ditagih iuran' : 'Tidak ditagih' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 font-semibold">
                            @if ($m->is_active && $m->hasAccount() && ! $isAdminRow && ! $isMe)
                                <form method="POST" action="{{ route('members.role', [$organization, $m]) }}">
                                    @csrf @method('PATCH')
                                    <x-select-input name="role" onchange="this.form.submit()" class="!py-2 text-sm">
                                        <option value="anggota" @selected($m->role === 'anggota')>Anggota</option>
                                        <option value="editor" @selected($m->role === 'editor')>Editor</option>
                                    </x-select-input>
                                </form>
                            @endif

                            @if (! $m->is_active)
                                <form method="POST" action="{{ route('members.reactivate', [$organization, $m]) }}">
                                    @csrf @method('PATCH')
                                    <button class="text-brand hover:text-brand-dark">Aktifkan</button>
                                </form>
                            @elseif (! $isAdminRow && ! $isMe)
                                <form method="POST" action="{{ route('members.deactivate', [$organization, $m]) }}"
                                      onsubmit="return confirm('Nonaktifkan {{ $m->name }}? Riwayat tagihannya tetap tersimpan.')">
                                    @csrf @method('PATCH')
                                    <button class="text-expense hover:opacity-80">Nonaktifkan</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Ubah data --}}
                    <details class="mt-3">
                        <summary class="cursor-pointer font-semibold text-brand hover:text-brand-dark">Ubah data</summary>
                        <form method="POST" action="{{ route('members.update', [$organization, $m]) }}"
                              class="mt-3 grid items-end gap-3 md:grid-cols-4">
                            @csrf @method('PATCH')
                            <div>
                                <x-input-label value="Nama" />
                                <x-text-input name="name" class="mt-1 block w-full" :value="$m->name" required />
                            </div>
                            <div>
                                <x-input-label value="Email" />
                                @if ($m->hasAccount())
                                    <div class="mt-1 py-2 text-mute">{{ $m->email }} (mengikuti akun)</div>
                                @else
                                    <x-text-input name="email" type="email" class="mt-1 block w-full" :value="$m->email" />
                                @endif
                            </div>
                            <div>
                                <x-input-label value="Telepon" />
                                <x-text-input name="phone" class="mt-1 block w-full" :value="$m->phone" />
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="hidden" name="is_billable" value="0">
                                    <input type="checkbox" name="is_billable" value="1" @checked($m->is_billable) class="rounded border-line text-brand focus:ring-brand">
                                    Ditagih
                                </label>
                                <x-primary-button>Simpan</x-primary-button>
                            </div>
                        </form>
                    </details>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>