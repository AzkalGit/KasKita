@php
    $labelPeran = ['admin' => 'Admin', 'editor' => 'Editor', 'anggota' => 'Anggota'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Organisasi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div role="status" class="p-4 rounded-lg bg-green-50 text-green-800 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Organisasi yang diikuti --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900">Organisasimu</h3>

                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($memberships as $m)
                        <div class="py-3 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900">{{ $m->organization->name }}</div>
                                <div class="text-sm text-gray-500">{{ $labelPeran[$m->role] ?? ucfirst((string) $m->role) }}</div>
                            </div>

                            @if ($m->organization->isActive())
                                <a href="{{ route('organizations.dashboard', $m->organization) }}"
                                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                    Buka
                                </a>
                            @else
                                <span class="text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </div>
                    @empty
                        <p class="py-3 text-sm text-gray-600">
                            Kamu belum tergabung di organisasi mana pun. Buat organisasi baru atau gabung dengan kode undangan di bawah.
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                {{-- Buat organisasi: pembuat otomatis menjadi Admin --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">Buat organisasi baru</h3>
                    <p class="mt-1 text-sm text-gray-600">Kamu otomatis menjadi Admin dan bisa mengundang anggota.</p>

                    <form method="POST" action="{{ route('organizations.store') }}" class="mt-4">
                        @csrf
                        <x-input-label for="name" value="Nama organisasi" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name')" maxlength="255" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />

                        <x-primary-button class="mt-4">Buat organisasi</x-primary-button>
                    </form>
                </div>

                {{-- Gabung lewat kode undangan: peran selalu Anggota --}}
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900">Gabung dengan kode</h3>
                    <p class="mt-1 text-sm text-gray-600">Minta kode undangan ke Admin organisasi. Kamu bergabung sebagai Anggota.</p>

                    <form method="POST" action="{{ route('organizations.join') }}" class="mt-4">
                        @csrf
                        <x-input-label for="code" value="Kode undangan" />
                        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full uppercase tracking-widest"
                                      maxlength="20" autocomplete="off" required />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />

                        <x-primary-button class="mt-4">Gabung</x-primary-button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
