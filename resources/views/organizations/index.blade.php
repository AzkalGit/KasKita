<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Organisasi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-green-50 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            {{-- Daftar organisasi milikku --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="font-semibold mb-4">Organisasi yang kamu ikuti</h3>

                @forelse ($memberships as $m)
                    <div class="flex items-center justify-between border-b py-3 last:border-0">
                        <div>
                            <div class="font-medium">{{ $m->organization->name }}</div>
                            <div class="text-sm text-gray-500">
                                Peran: {{ ucfirst($m->role) }}
                                @unless ($m->organization->isActive())
                                    · <span class="text-red-600">nonaktif</span>
                                @endunless
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            @if ($m->organization->isActive())
                                <a href="{{ route('organizations.dashboard', $m->organization) }}"
                                   class="inline-flex items-center px-4 py-2 bg-green-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                    Buka
                                </a>

                                {{-- Admin belum bisa keluar (serah-terima belum ada) --}}
                                @if ($m->role !== 'admin')
                                    <form method="POST" action="{{ route('members.leave', $m->organization) }}"
                                          onsubmit="return confirm({{ Js::from('Keluar dari '.$m->organization->name.'?') }})">
                                        @csrf
                                        <button class="text-sm text-red-600 font-medium">Keluar</button>
                                    </form>
                                @endif
                            @else
                                <span class="text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500">Kamu belum tergabung di organisasi mana pun. Buat organisasi baru atau gabung dengan kode di bawah.</p>
                @endforelse
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                {{-- Buat organisasi --}}
                <form method="POST" action="{{ route('organizations.store') }}" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold">Buat organisasi baru</h3>
                    <div>
                        <x-input-label for="name" value="Nama organisasi" />
                        <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name')" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <x-primary-button>Buat</x-primary-button>
                </form>

                {{-- Gabung dengan kode --}}
                <form method="POST" action="{{ route('organizations.join') }}" class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                    @csrf
                    <h3 class="font-semibold">Gabung dengan kode</h3>
                    <div>
                        <x-input-label for="code" value="Kode undangan" />
                        <x-text-input id="code" name="code" class="block mt-1 w-full uppercase" :value="old('code')" required />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <x-primary-button>Gabung</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>