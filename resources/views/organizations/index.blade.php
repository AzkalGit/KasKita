<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Organisasi</h1>
        <p class="mt-0.5 text-mute">Pilih organisasi yang ingin dibuka, atau buat dan gabung ke organisasi baru.</p>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif

        {{-- Daftar organisasi milikku --}}
        <div class="rounded-2xl border border-line bg-white p-5">
            <h3 class="mb-2 text-base font-bold">Organisasi yang kamu ikuti</h3>

            @forelse ($memberships as $m)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line py-3.5 last:border-0">
                    <div>
                        <div class="font-semibold">{{ $m->organization->name }}</div>
                        <div class="text-xs text-mute">
                            Peran: {{ ucfirst($m->role) }}
                            @unless ($m->organization->isActive())
                                · <span class="text-expense">nonaktif</span>
                            @endunless
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        @if ($m->organization->isActive())
                            <a href="{{ route('organizations.dashboard', $m->organization) }}"
                               class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white transition hover:bg-brand-dark">Buka</a>

                            {{-- Admin belum bisa keluar (serah-terima belum ada) --}}
                            @if ($m->role !== 'admin')
                                <form method="POST" action="{{ route('members.leave', $m->organization) }}"
                                      onsubmit="return confirm({{ Js::from('Keluar dari '.$m->organization->name.'?') }})">
                                    @csrf
                                    <button class="text-sm font-semibold text-expense hover:opacity-80">Keluar</button>
                                </form>
                            @endif
                        @else
                            <x-badge tone="gray">Nonaktif</x-badge>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-mute">Kamu belum tergabung di organisasi mana pun. Buat organisasi baru atau gabung dengan kode di bawah.</p>
            @endforelse
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Buat organisasi --}}
            <form method="POST" action="{{ route('organizations.store') }}" class="space-y-4 rounded-2xl border border-line bg-white p-5">
                @csrf
                <h3 class="text-base font-bold">Buat organisasi baru</h3>
                <div>
                    <x-input-label for="name" value="Nama organisasi" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <x-primary-button>Buat</x-primary-button>
            </form>

            {{-- Gabung dengan kode --}}
            <form method="POST" action="{{ route('organizations.join') }}" class="space-y-4 rounded-2xl border border-line bg-white p-5">
                @csrf
                <h3 class="text-base font-bold">Gabung dengan kode</h3>
                <div>
                    <x-input-label for="code" value="Kode undangan" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full uppercase" :value="old('code')" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <x-primary-button>Gabung</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>